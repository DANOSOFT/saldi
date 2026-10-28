<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261027 CL/LH SST-884 insert_shop_order() escaped every shop value and then ran chk4utf8() on the
//                finished SQL. chk4utf8() calls html_entity_decode(), which on PHP 8.1+ turns &#39;
//                back into a bare ' inside the quoted value: O&#39;Brien broke the insert and the
//                order was dropped. Raised by CodeRabbit on PR #861. The function now decodes each
//                value before escaping it and leaves the finished SQL alone.
//                insert_shop_order() lives in a page script (api/rest_api.php runs its request
//                dispatch on include), so the functions are lifted out by token and evaluated in a
//                namespace whose db_* stubs record the SQL; the INSERT is then read back the way
//                PostgreSQL reads quoted literals.
// 20261028 CL/SZ Sync of master into hml/shopify: insert_shop_order() there also calls shopify_legacy_effective_afd() (SD-750), so the fixture gets a stub with the same body as includes/shopify/coexistence.php.
final class ShopOrderHtmlEntityTest extends TestCase
{
	private const NS = 'ShopOrderHtmlEntityFixture';

	/** @var mixed */
	private $previousDb;

	public static function setUpBeforeClass(): void
	{
		if (function_exists(self::NS . '\insert_shop_order')) {
			return;
		}
		$wanted = array('chk4utf8', 'shop_input_text', 'shop_order_cvr_ean_from_card', 'insert_shop_order');
		$code = self::topLevelFunctions(__DIR__ . '/../../../api/rest_api.php', $wanted);
		eval('namespace ' . self::NS . ';' . self::STUBS . $code);
	}

	protected function setUp(): void
	{
		$this->previousDb = $GLOBALS['db'] ?? null;
		$GLOBALS['db'] = 'fixture_1';
		$class = self::NS . '\Db';
		$class::$writes = array();
	}

	protected function tearDown(): void
	{
		$GLOBALS['db'] = $this->previousDb;
	}

	/** The reported case: an existing customer, apostrophes sent as entities, Latin-1 bytes in the name. */
	public function testEncodedApostropheReachesTheOrderAsAnApostrophe(): void
	{
		$result = $this->placeOrder(array(
			'firmanavn' => "S\xF8ren &amp; Co",
			'addr1'     => 'Caf&eacute;vej 1',
			'ekstra1'   => 'O&#39;Brien',
			'ref'       => 'Ref&#039;1',
		));

		$order = $this->insertedRow('ordrer');
		self::assertNotNull($order, 'insert into ordrer must be valid SQL: ' . $this->insertSql('ordrer'));
		self::assertSame('99', (string)$result, 'the order is created');
		self::assertSame("O'Brien", $order['felt_1']);
		self::assertSame("Ref'1", $order['ref']);
		self::assertSame('Søren & Co', $order['firmanavn']);
		self::assertSame('Cafévej 1', $order['addr1']);
		self::assertSame('12345678', $order['cvrnr'], 'SST-884 card CVR still fills an empty shop CVR');
	}

	/** A new customer is created from the same shop fields, so its card insert had the same defect. */
	public function testEncodedApostropheReachesANewCustomerCard(): void
	{
		$this->placeOrder(array('saldi_kontonr' => '2002', 'firmanavn' => 'O&#39;Reilly ApS'));

		$card = $this->insertedRow('adresser');
		self::assertNotNull($card, 'insert into adresser must be valid SQL: ' . $this->insertSql('adresser'));
		self::assertSame("O'Reilly ApS", $card['firmanavn']);
		self::assertSame('2002', $card['kontonr']);
		$order = $this->insertedRow('ordrer');
		self::assertNotNull($order, 'insert into ordrer must be valid SQL: ' . $this->insertSql('ordrer'));
		self::assertSame("O'Reilly ApS", $order['firmanavn']);
	}

	/**
	 * Entities are decoded exactly once, and valid UTF-8 outside the Danish letters is stored as sent.
	 * chk4utf8() re-encoded the whole statement from ISO-8859-1 when it found no Danish letter in it,
	 * which garbled a name like Łukasz; nothing re-encodes the statement now.
	 */
	public function testEntitiesAreDecodedOnceAndUtf8IsKept(): void
	{
		$this->placeOrder(array('kontakt' => 'Łukasz', 'notes' => 'Ring &#8217;n &amp;#39;'));

		$order = $this->insertedRow('ordrer');
		self::assertNotNull($order, 'insert into ordrer must be valid SQL: ' . $this->insertSql('ordrer'));
		self::assertSame('Łukasz', $order['kontakt']);
		self::assertSame('Ring ’n &#39;', $order['notes']);
	}

	/**
	 * Calls insert_shop_order() with a plain webshop order for account 1001 (an existing card with
	 * CVR 12345678), with the given parameters replaced.
	 *
	 * @param array<string, mixed> $overrides Parameter name => value.
	 * @return mixed What insert_shop_order() returns: the new order id, or an error text.
	 */
	private function placeOrder(array $overrides)
	{
		$defaults = array(
			'brugernavn' => 'api', 'shopOrderId' => '1234', 'shop_fakturanr' => '1234', 'shop_addr_id' => '55',
			'saldi_kontonr' => '1001', 'firmanavn' => 'Test ApS', 'addr1' => 'Vej 1', 'addr2' => '',
			'postnr' => '8000', 'bynavn' => 'Aarhus C', 'land' => 'Denmark', 'cvrnr' => '', 'ean' => '',
			'institution' => '', 'tlf' => '12345678', 'email' => 'kunde@example.dk', 'udskriv_til' => 'email',
			'ref' => 'Ref', 'kontakt' => 'Kontakt', 'lev_firmanavn' => '', 'lev_addr1' => '', 'lev_addr2' => '',
			'lev_postnr' => '', 'lev_bynavn' => '', 'lev_land' => '', 'lev_tlf' => '', 'lev_email' => '',
			'lev_kontakt' => '', 'betalingsbet' => 'Netto', 'betalingsdage' => '8', 'betalings_id' => '',
			'ordredate' => '2026-10-27 10:15', 'lev_date' => '', 'momssats' => '25', 'valuta' => 'DKK',
			'valutakurs' => '100', 'gruppe' => '1', 'afd' => 0, 'projekt' => '', 'ekstra1' => '', 'ekstra2' => '',
			'ekstra3' => '', 'ekstra4' => '', 'ekstra5' => '', 'nettosum' => 0, 'momssum' => 0, 'lager' => 1,
			'shop_status' => '', 'notes' => '', 'sprog' => 'Dansk', 'art' => 'DO',
		);
		$args = array();
		foreach ((new ReflectionFunction(self::NS . '\insert_shop_order'))->getParameters() as $parameter) {
			$name = $parameter->getName();
			self::assertArrayHasKey($name, $defaults, "insert_shop_order() has a parameter the test does not know: $name");
			$args[] = array_key_exists($name, $overrides) ? $overrides[$name] : $defaults[$name];
		}
		return call_user_func_array(self::NS . '\insert_shop_order', $args);
	}

	private function insertSql(string $table): string
	{
		$class = self::NS . '\Db';
		foreach ($class::$writes as $sql) {
			if (preg_match('/^insert into ' . $table . '\b/i', $sql)) {
				return $sql;
			}
		}
		self::fail("no insert into $table was written");
	}

	/**
	 * Column => value of the single-row INSERT into $table, reading the quoted literals the way
	 * PostgreSQL does ('' is one quote). Null when the statement does not parse: an unescaped quote
	 * ends a literal early, which is how the defect showed.
	 *
	 * @param string $table Table name.
	 * @return array<string, string>|null
	 */
	private function insertedRow(string $table): ?array
	{
		$sql = $this->insertSql($table);
		if (!preg_match('/^insert into \w+\s*\(([^)]*)\)\s*values\s*\(/i', $sql, $m)) {
			return null;
		}
		$columns = array_map('trim', explode(',', $m[1]));
		$pos = strlen($m[0]);
		$values = array();
		while (true) {
			if (($sql[$pos] ?? '') !== "'") {
				return null;
			}
			$pos++;
			$value = '';
			while (true) {
				$quote = strpos($sql, "'", $pos);
				if ($quote === false) {
					return null;
				}
				$value .= substr($sql, $pos, $quote - $pos);
				$pos = $quote + 1;
				if (($sql[$pos] ?? '') !== "'") {
					break;
				}
				$value .= "'";
				$pos++;
			}
			$values[] = $value;
			if (($sql[$pos] ?? '') === ',') {
				$pos++;
				continue;
			}
			if (($sql[$pos] ?? '') === ')' && trim(substr($sql, $pos + 1)) === '') {
				break;
			}
			return null;
		}
		return count($columns) === count($values) ? array_combine($columns, $values) : null;
	}

	/**
	 * Source of the named top-level functions in a file, cut out by token.
	 *
	 * @param string $file Path to the PHP file.
	 * @param list<string> $names Functions to take; a name the file lacks is skipped.
	 * @return string
	 */
	private static function topLevelFunctions(string $file, array $names): string
	{
		$tokens = token_get_all((string)file_get_contents($file));
		$count = count($tokens);
		$depth = 0;
		$code = '';
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			if ($depth === 0 && is_array($token) && $token[0] === T_FUNCTION) {
				$j = $i + 1;
				while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
					$j++;
				}
				if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && in_array($tokens[$j][1], $names, true)) {
					$source = '';
					$level = 0;
					for ($k = $i; $k < $count; $k++) {
						$text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
						$source .= $text;
						if ($text === '{' || (is_array($tokens[$k]) && in_array($tokens[$k][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
							$level++;
						} elseif ($text === '}') {
							$level--;
							if ($level === 0) {
								break;
							}
						}
					}
					$code .= $source . "\n";
					$i = $k;
					continue;
				}
			}
			$text = is_array($token) ? $token[1] : $token;
			if ($text === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
			} elseif ($text === '}') {
				$depth--;
			}
		}
		return $code;
	}

	/**
	 * Stand-ins for what insert_shop_order() calls, declared in the fixture namespace so they win over
	 * any global function of the same name. db_escape_string() behaves like pg_escape_string() with
	 * standard_conforming_strings on, after the same NUL strip and Windows-1252 fallback as
	 * includes/db_query.php. Selects are answered by the first matching needle.
	 */
	private const STUBS = <<<'PHP'
		final class Db {
			/** @var list<string> */
			public static $writes = array();
			/** @var list<array{0: string, 1: list<array<string, string>>}> */
			public static $routes = array(
				array('information_schema', array(array('data_type' => 'character varying', 'column_name' => 'afd'))),
				array('from shop_ordrer', array(array('id' => '', 'saldi_id' => ''))),
				array('from shop_adresser', array(array('saldi_id' => ''))),
				array("from adresser where kontonr='1001'", array(array('id' => '7', 'kontonr' => '1001'))),
				array('select id from adresser where kontonr', array(array('id' => '8'))),
				array('select cvrnr, ean from adresser', array(array('cvrnr' => '12345678', 'ean' => '5790000000001'))),
				array("art='DG'", array(array('box1' => 'S1'))),
				array("art='SM'", array(array('box2' => '25'))),
				array('select id from ordrer', array(array('id' => '99'))),
			);
		}
		function db_escape_string($value) {
			$value = str_replace("\0", '', (string)$value);
			if (!mb_check_encoding($value, 'UTF-8')) {
				$value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
			}
			return str_replace("'", "''", $value);
		}
		function db_select($sql, $where = '') {
			foreach (Db::$routes as $route) {
				if (stripos($sql, $route[0]) !== false) {
					return new \ArrayIterator($route[1]);
				}
			}
			return new \ArrayIterator(array());
		}
		function db_fetch_array($result) {
			if (!$result->valid()) {
				return false;
			}
			$row = $result->current();
			$result->next();
			return $row;
		}
		function db_modify($sql, $where = '') {
			Db::$writes[] = $sql;
			return true;
		}
		function get_next_order_number($art) {
			return 501;
		}
		function get_ip() {
			return '127.0.0.1';
		}
		function shopify_legacy_effective_afd($afd, $ip) {
			return $ip === '172.105.246.144' ? 4 : (int)$afd;
		}
		function usdecimal($value) {
			return str_replace(',', '.', $value);
		}
		function sleep($seconds) {
			return 0;
		}
		function fopen($path, $mode) {
			return \fopen('php://memory', 'w+');
		}

		PHP;
}
