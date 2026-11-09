<?php

use PHPUnit\Framework\TestCase;

/** Exercise the page's date-warning condition without rendering or writing to a tenant. */
final class JournalDateWarningTest extends TestCase
{
	public function testMissingOrZeroPreviousBalanceDoesNotWarn(): void
	{
		$source = file_get_contents(__DIR__ . '/../../../finans/kassekladde.php');
		self::assertSame(1, preg_match('/if \(([^\n]+\$forskellige_datoer[^\n]+)\) \{/', $source, $matches));
		$condition = $matches[1];
		$x = 2;
		$forskellige_datoer = false;
		$bilag = array(1 => '12', 2 => '12');
		$dato = array(1 => '01-11-2026', 2 => '02-11-2026');

		set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
			throw new ErrorException($message, 0, $severity, $file, $line);
		});
		try {
			foreach (array(array(), array(1 => 0), array(1 => 25)) as $bilagssum) {
				$result = eval('return ' . $condition . ';');
				self::assertSame(isset($bilagssum[1]) && $bilagssum[1] != 0, $result);
			}
		} finally {
			restore_error_handler();
		}
	}
}
