<?php
// 20260908 CDX/LH Regression coverage for optional order performers (SD-558).

namespace Saldi\Tests\PerformedBy;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../debitor/orderIncludes/performedBy.php';

function db_select($sql, $location)
{
	$GLOBALS['performedByQueries'][] = $sql;
	return array('hvem' => 'Stored technician');
}

function db_fetch_array($row)
{
	return $row;
}

final class PerformedByTest extends TestCase
{
	public function testBlankIsDefaultEvenWhenCurrentUserIsAnEmployee(): void
	{
		$options = $this->options(null, array('Alice', 'Bob'), 'Alice');
		self::assertSame('', $options->query('//option[@selected]')->item(0)->getAttribute('value'));
		self::assertCount(3, $options->query('//option'));
	}

	public function testHistoricalNameRemainsSelectedAndCanBeClearedWithoutEmployees(): void
	{
		$options = $this->options('Former technician', array(), 'Alice');
		self::assertSame('Former technician', $options->query('//option[@selected]')->item(0)->getAttribute('value'));
		self::assertCount(1, $options->query('//option[@value=""]'));
		$cleared = $this->options('', array(), 'Alice');
		self::assertSame('', $cleared->query('//option[@selected]')->item(0)->getAttribute('value'));
	}

	public function testNamesRoundTripWithoutMarkupOrDuplicateChoices(): void
	{
		$name = 'O\'Brien "<script>" & Æøå';
		$options = $this->options($name, array($name, $name), $name);
		self::assertCount(2, $options->query('//option'));
		self::assertCount(0, $options->query('//script'));
		self::assertSame($name, $options->query('//option[@selected]')->item(0)->getAttribute('value'));
		self::assertSame($name, $options->query('//option[@selected]')->item(0)->textContent);
	}

	public function testSavePreservesMissingValueButAcceptsExplicitBlankAndZero(): void
	{
		$source = file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
		$start = strpos($source, '// A missing field preserves');
		$end = strpos($source, 'if (strlen($levdate)', $start);
		$block = substr($source, $start, $end - $start);
		foreach (array(null, '', '0', "O'Brien") as $submitted) {
			$GLOBALS['performedByQueries'] = array();
			$hvem = $submitted;
			$id = 42;
			eval('namespace ' . __NAMESPACE__ . '; ' . $block);
			self::assertSame($submitted ?? 'Stored technician', $hvem);
			self::assertCount($submitted === null ? 1 : 0, $GLOBALS['performedByQueries']);
		}
	}

	public function testCustomerUpdateDoesNotOverwritePerformerBeforePostIsRead(): void
	{
		$source = file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
		$start = strpos($source, '} elseif ($status < 3 && $id && $firmanavn)');
		$end = strpos($source, 'db_modify($qtxt', $start);
		$block = substr($source, $start, $end - $start);
		self::assertStringNotContainsString("hvem = '", $block);
		self::assertStringNotContainsString("hvem='", $block);
	}

	private function options($selected, array $employees, $username): \DOMXPath
	{
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="UTF-8"><select>' . \performedByOptions($selected, $employees, $username) . '</select>');
		return new \DOMXPath($document);
	}
}
