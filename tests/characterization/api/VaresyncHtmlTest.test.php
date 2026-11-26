<?php
// tests/characterization/api/VaresyncHtmlTest.test.php
//
// The webshop sync's messages escape item numbers and shop values in the page's own charset
// (varesyncHtml() in api/varesync.php). Without the charset PHP assumes UTF-8, so on a LATIN9
// install an item number with æ, ø or å was shown with a replacement character.
//
// History:
// 20261126 CL/SZ MB-110 Created.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/api/varesync.php';

final class VaresyncHtmlTest extends TestCase
{
	protected function tearDown(): void
	{
		unset($GLOBALS['charset']);
	}

	public function test_a_latin9_item_number_keeps_its_letters(): void
	{
		$GLOBALS['charset'] = 'ISO-8859-1';
		$this->assertSame("R\xf8d &#039;A&#039; &amp; B", varesyncHtml("R\xf8d 'A' & B"));
	}

	public function test_a_utf8_item_number_keeps_its_letters(): void
	{
		$GLOBALS['charset'] = 'UTF-8';
		$this->assertSame("R\u{f8}d &lt;b&gt;", varesyncHtml("R\u{f8}d <b>"));
	}

	public function test_without_a_charset_it_reads_utf8_and_null_is_empty(): void
	{
		$this->assertSame("R\u{f8}d", varesyncHtml("R\u{f8}d"));
		$this->assertSame('', varesyncHtml(null));
	}
}
