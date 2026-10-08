<?php
// 20261006 CL/LH SST-838: Regress the bank import charset handling that turned æøå into '?'.

/**
 * Run with: php tests/test_bank_import_encoding.php
 *
 * Exercises includes/stdFunc/bankImportEncoding.php directly with byte strings and temporary files.
 * No database, session or credentials are needed.
 */

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
require __DIR__ . '/../includes/stdFunc/bankImportEncoding.php';

$failures = 0;

/**
 * Prints PASS or FAIL for one case and counts failures.
 *
 * @param string $name     Case description.
 * @param string $expected Expected bytes.
 * @param string $actual   Actual bytes.
 * @return void
 */
function check(string $name, string $expected, string $actual): void
{
	global $failures;
	if ($expected === $actual) {
		echo "PASS: $name\n";
		return;
	}
	$failures++;
	echo "FAIL: $name\n      expected " . bin2hex($expected) . "\n      actual   " . bin2hex($actual) . "\n";
}

/**
 * Writes $content to a temporary file and returns the decoded lines from bank_import_read_lines().
 *
 * @param string $content Raw file bytes.
 * @param string $charset Tenant charset.
 * @return array<int, string> Decoded lines.
 */
function read_fixture(string $content, string $charset): array
{
	$filename = tempnam(sys_get_temp_dir(), 'sst838');
	file_put_contents($filename, $content);
	try {
		$lines = bank_import_read_lines($filename, $charset);
	} finally {
		unlink($filename);
	}
	if ($lines === false) throw new RuntimeException('Fixture could not be read');
	return $lines;
}

$cp1252 = function (string $utf8): string {
	return mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
};

// Line-level decoding for a UTF8 tenant.
check('pure Windows-1252 line', '30.09.2026;Løn;Ærø Å', bank_import_decode_line($cp1252('30.09.2026;Løn;Ærø Å'), 'UTF-8'));
check('UTF-8 line without BOM', '811714 Ønsk;Lemvigh-Møller', bank_import_decode_line('811714 Ønsk;Lemvigh-Møller', 'UTF-8'));
check('UTF-8 line with BOM', 'Dato;Valør;Tekst', bank_import_decode_line("\xEF\xBB\xBFDato;Valør;Tekst", 'UTF-8'));
check('UTF-8 line with only æ and å is not double encoded', 'Lapning af dæk;Århus', bank_import_decode_line('Lapning af dæk;Århus', 'UTF-8'));
check('Ø at position 0 (UTF-8)', 'Ønsk', bank_import_decode_line('Ønsk', 'UTF-8'));
check('Ø at position 0 (Windows-1252)', 'Ønsk', bank_import_decode_line($cp1252('Ønsk'), 'UTF-8'));
check('æøå first and last on the line are kept (Windows-1252)', 'å;Malmö;ø', trim(bank_import_decode_line($cp1252("å;Malmö;ø") . "\r\n", 'UTF-8')));
check('æøå first and last on the line are kept (UTF-8)', 'Æ;Malmö;Å', trim(bank_import_decode_line("Æ;Malmö;Å\n", 'UTF-8')));
check('euro sign 0x80 in Windows-1252', '€ 100', bank_import_decode_line("\x80 100", 'UTF-8'));
check('dash and typographic quotes in Windows-1252', '– “x”', bank_import_decode_line("\x96 \x93x\x94", 'UTF-8'));
check('one line mixing UTF-8 and Windows-1252', 'Smørum;Løn;Køge', bank_import_decode_line("Sm\xC3\xB8rum;L\xF8n;K\xC3\xB8ge", 'UTF-8'));
check('non-breaking space 0xA0 in Windows-1252', "1\u{A0}000,00", bank_import_decode_line("1\xA0000,00", 'UTF-8'));
check('non-breaking space in UTF-8', "1\u{A0}000,00", bank_import_decode_line("1\xC2\xA0000,00", 'UTF-8'));
check('URL-encoded %c3%xx text is left for the field parser', 'Sm%c3%b8rum', bank_import_decode_line('Sm%c3%b8rum', 'UTF-8'));
check('a literal question mark is kept', '?Hvad?', bank_import_decode_line('?Hvad?', 'UTF-8'));
check('truncated UTF-8 lead byte is read as Windows-1252', 'Ã', bank_import_decode_line("\xC3", 'UTF-8'));

// Line-level decoding for a LATIN9 tenant: output must be ISO-8859-15 bytes.
check('LATIN9: Windows-1252 æøå', "L\xF8n \xE6\xE5 \xC6\xD8\xC5", bank_import_decode_line($cp1252('Løn æå ÆØÅ'), 'ISO-8859-1'));
check('LATIN9: UTF-8 æøå', "L\xF8n \xE6\xE5 \xC6\xD8\xC5", bank_import_decode_line('Løn æå ÆØÅ', 'ISO-8859-1'));
check('LATIN9: euro becomes ISO-8859-15 0xA4', "\xA4 100", bank_import_decode_line("\x80 100", 'ISO-8859-1'));
check('LATIN9: BOM removed and Ø at position 0 kept', "\xD8nsk", bank_import_decode_line("\xEF\xBB\xBFØnsk", 'ISO-8859-1'));

// File-level reading: the customer case is a Windows-1252 file with a single UTF-8 line.
$customerFile = $cp1252("Dato;Valør;Tekst;;Beløb;Saldo;Egen bilagsreference\r\n")
	. $cp1252("30.09.2026;30.09.2026;Fak.No. 811714;;621,88;0,00;811714 Ønsk\r\n")
	. "Afsender;Sm\xC3\xB8rum\r\n"
	. $cp1252("29.09.2026;29.09.2026;Løn;;-1000,00;0,00;Løn\r\n")
	. $cp1252("28.09.2026;28.09.2026;Asa;;-50,00;0,00;8I02981 Asa i Malmö\r\n")
	. $cp1252("27.09.2026;27.09.2026;Gebyr;;-5,00;0,00;Danløn gebyr");
$lines = read_fixture($customerFile, 'UTF-8');
$descriptions = array();
foreach ($lines as $line) {
	$fields = explode(';', trim($line));
	$descriptions[] = $fields[6] ?? $fields[1];
}
check('customer file: every line kept with its line ending', '6', (string)count($lines));
check('customer file: all æøå survive next to a UTF-8 line', 'Egen bilagsreference|811714 Ønsk|Smørum|Løn|8I02981 Asa i Malmö|Danløn gebyr', implode('|', $descriptions));
check('customer file: header keeps ø in Valør and Beløb', 'Dato;Valør;Tekst;;Beløb;Saldo;Egen bilagsreference', trim($lines[0]));

$lines = read_fixture("\xEF\xBB\xBF811714 Ønsk;Løn\n811729 Ønsk;Ærø\n", 'UTF-8');
check('UTF-8 file with BOM', '811714 Ønsk;Løn|811729 Ønsk;Ærø', trim($lines[0]) . '|' . trim($lines[1]));

$lines = read_fixture("\xFF\xFE" . mb_convert_encoding("Dato;Tekst\r\n01.09.2026;Løn ÆØÅ\r\n", 'UTF-16LE', 'UTF-8'), 'UTF-8');
check('UTF-16LE file with BOM is decoded before it is split', 'Dato;Tekst|01.09.2026;Løn ÆØÅ', trim($lines[0]) . '|' . trim($lines[1]));

$lines = read_fixture("\xFE\xFF" . mb_convert_encoding("Dato;Tekst\n01.09.2026;Køge\n", 'UTF-16BE', 'UTF-8'), 'UTF-8');
check('UTF-16BE file with BOM is decoded before it is split', 'Dato;Tekst|01.09.2026;Køge', trim($lines[0]) . '|' . trim($lines[1]));

$lines = read_fixture($cp1252("a;ø\rb;æ\r\nc;å\n\nd;Å"), 'UTF-8');
check('CR, CRLF and LF line endings and blank lines', "a;ø\r|b;æ\r\n|c;å\n|\n|d;Å", implode('|', $lines));

$lines = read_fixture($cp1252("01.09.2026;Løn ÆØÅ €\n"), 'ISO-8859-1');
check('LATIN9 file read gives ISO-8859-15 bytes', "01.09.2026;L\xF8n \xC6\xD8\xC5 \xA4", trim($lines[0]));

// The three readers must use the helper and no longer guess one charset for the whole file.
foreach (array('finans/bankimport.php', 'finans/bankReconcile.php', 'finans/rapport_includes/bankReconcile.php') as $reader) {
	$source = file_get_contents(__DIR__ . '/../' . $reader);
	$legacy = preg_match('/\$tegnsaet\s*=|trim\(\$linje,\s*"\?"\)/', $source);
	$usesHelper = strpos($source, 'bank_import_read_lines($filnavn, $charset)') !== false;
	check("$reader uses bank_import_read_lines() without the whole-file guess", '1', (string)(int)(!$legacy && $usesHelper));
}

if ($failures) {
	echo "$failures case(s) FAILED\n";
	exit(1);
}
echo "All bank import encoding cases passed\n";
