<?php
// tests/shopify/support/GraphqlDocument.php
//
// A parsed GraphQL document for FakeShopifyStore (SD-766): operations with their variable
// definitions, fragments, and every field with alias, arguments and sub-selection, read with
// GraphqlSchema's tokenizer. project() turns a resolved value into the response the selection
// asks for: aliases, inline and named fragments, __typename, @include/@skip, and connections
// (nodes, edges, pageInfo) made from plain lists by the schema's field type. A field the model
// lacks fails loudly instead of answering null.
//
// History:
// 20261102 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: fields() and included() are public, for FakeShopifyStore's cost and refusals.

require_once __DIR__ . '/GraphqlSchema.php';

/** A request Shopify would refuse as a whole: answered as {"errors": [...]}, no data. */
final class GraphqlRequestError extends RuntimeException
{
	public function __construct(string $message, public readonly string $errorCode = 'BAD_REQUEST')
	{
		parent::__construct($message);
	}
}

final class GraphqlDocument
{
	/** Shopify's page size limit for a connection's first or last. */
	public const MAX_PAGE = 250;

	/** @var list<array{type: string, name: ?string, variables: array<string, array{type: string, default: mixed}>, selections: list<array>}> */
	public array $operations = [];
	/** @var array<string, array{on: string, selections: list<array>}> */
	public array $fragments = [];
	/** @var array<string, true> variables used anywhere in the document */
	public array $used = [];

	/** @var list<array{0: string, 1: string}> */
	private array $tokens;
	private int $pos = 0;

	private function __construct(string $document)
	{
		$this->tokens = GraphqlSchema::tokenize($document);
	}

	public static function parse(string $document): self
	{
		$doc = new self($document);
		while ($doc->peek() !== null) {
			$doc->definition();
		}
		return $doc;
	}

	/** The one operation of the document; Shopify needs operationName for more, which the client never sends. */
	public function operation(): array
	{
		if (count($this->operations) !== 1) {
			throw new GraphqlRequestError('the document must hold exactly one operation, it has ' . count($this->operations));
		}
		return $this->operations[0];
	}

	/**
	 * The variables of the operation after defaults, checked as GraphQL does: every use is
	 * defined, every definition is used, required ones are given, and each value fits its type.
	 *
	 * @return array<string, mixed>
	 */
	public function variables(array $given, GraphqlSchema $schema): array
	{
		$op = $this->operation();
		$errors = [];
		foreach (array_keys($this->used) as $name) {
			if (!isset($op['variables'][$name])) {
				$errors[] = "Variable \$$name is used by {$this->label($op)} but not declared";
			}
		}
		$out = [];
		foreach ($op['variables'] as $name => $def) {
			if (!isset($this->used[$name])) {
				$errors[] = "Variable \$$name is declared by {$this->label($op)} but not used";
			}
			$value = array_key_exists($name, $given) ? $given[$name] : $def['default'];
			foreach ($schema->validateInput($def['type'], $value, "\$$name") as $problem) {
				$errors[] = $problem;
			}
			$out[$name] = $value;
		}
		if ($errors) {
			throw new GraphqlRequestError(implode('; ', $errors));
		}
		return $out;
	}

	/** Resolves an argument value node against the variables. */
	public static function value(array $node, array $vars): mixed
	{
		if (array_key_exists('var', $node)) {
			return $vars[$node['var']] ?? null;
		}
		if (array_key_exists('list', $node)) {
			return array_map(fn(array $item): mixed => self::value($item, $vars), $node['list']);
		}
		if (array_key_exists('object', $node)) {
			return array_map(fn(array $item): mixed => self::value($item, $vars), $node['object']);
		}
		return $node['enum'] ?? $node['literal'];
	}

	/** @return array<string, mixed> the field's arguments, resolved */
	public static function args(array $field, array $vars): array
	{
		return array_map(fn(array $node): mixed => self::value($node, $vars), $field['args']);
	}

	/**
	 * The root fields of the operation that are selected, after @include/@skip and fragments.
	 *
	 * @return list<array> field nodes
	 */
	public function rootFields(array $vars): array
	{
		return $this->collect($this->operation()['selections'], null, $vars);
	}

	/**
	 * Projects $value of schema type $type onto $selections. Objects are arrays, optionally with
	 * __typename (it wins over $type for interfaces) and Closure values, called with the field's
	 * arguments for computed fields; a plain list under a *Connection field becomes the connection.
	 */
	public function project(mixed $value, ?array $selections, string $type, array $vars, GraphqlSchema $schema, string $path): mixed
	{
		if ($value === null || $selections === null) {
			return $value;
		}
		if (is_array($value) && array_is_list($value)) {
			$out = [];
			foreach ($value as $i => $item) {
				$out[] = $this->project($item, $selections, $type, $vars, $schema, "$path.$i");
			}
			return $out;
		}
		if (!is_array($value)) {
			throw new LogicException("FakeShopifyStore: $path is a scalar but the document selects fields of it");
		}
		$actual = (string)($value['__typename'] ?? $type);
		$out = [];
		foreach ($this->collect($selections, $actual, $vars) as $field) {
			$alias = $field['alias'];
			if ($field['name'] === '__typename') {
				$out[$alias] = $actual;
				continue;
			}
			$spec = $schema->fieldSpec($actual, $field['name']);
			if ($spec === null) {
				throw new LogicException("FakeShopifyStore: $actual has no field {$field['name']} ($path)");
			}
			if (!array_key_exists($field['name'], $value)) {
				throw new LogicException("FakeShopifyStore: $actual.{$field['name']} is not modelled ($path.$alias)");
			}
			$args = self::args($field, $vars);
			$resolved = $value[$field['name']];
			if ($resolved instanceof Closure) {
				$resolved = $resolved($args);
			}
			$named = trim($spec['type'], '[]!');
			if (str_ends_with($named, 'Connection') && is_array($resolved) && array_is_list($resolved)) {
				$resolved = self::connection($resolved, $args, $named);
			}
			$projected = $this->project($resolved, $field['selections'], $named, $vars, $schema, "$path.$alias");
			// Two selections of one alias merge, as GraphQL's field merging does.
			$out[$alias] = isset($out[$alias]) && is_array($out[$alias]) && is_array($projected)
				? array_replace_recursive($out[$alias], $projected) : $projected;
		}
		return $out;
	}

	/**
	 * A connection over $items with Shopify's paging (FakeShopifyBehaviours B19): first/after or last/before, reverse, at most
	 * MAX_PAGE a page; a connection without first or last is refused.
	 *
	 * @param list<array> $items
	 */
	public static function connection(array $items, array $args, string $type): array
	{
		$first = $args['first'] ?? null;
		$last = $args['last'] ?? null;
		if ($first === null && $last === null) {
			throw new GraphqlRequestError('you must provide one of first or last');
		}
		foreach (['first' => $first, 'last' => $last] as $name => $size) {
			if ($size !== null && ($size < 0 || $size > self::MAX_PAGE)) {
				throw new GraphqlRequestError("The value of $name must be between 0 and " . self::MAX_PAGE);
			}
		}
		if (!empty($args['reverse'])) {
			$items = array_reverse($items);
		}
		$start = isset($args['after']) ? self::cursorOffset((string)$args['after']) + 1 : 0;
		$end = isset($args['before']) ? self::cursorOffset((string)$args['before']) : count($items);
		if ($first !== null) {
			$end = min($end, $start + (int)$first);
		}
		if ($last !== null) {
			$start = max($start, $end - (int)$last);
		}
		$page = array_slice($items, $start, max(0, $end - $start));
		$edges = [];
		foreach ($page as $i => $node) {
			$edges[] = ['__typename' => substr($type, 0, -10) . 'Edge', 'cursor' => self::cursor($start + $i), 'node' => $node];
		}
		return [
			'__typename' => $type,
			'nodes' => $page,
			'edges' => $edges,
			'pageInfo' => [
				'__typename' => 'PageInfo',
				'hasNextPage' => $end < count($items),
				'hasPreviousPage' => $start > 0,
				'startCursor' => $page ? self::cursor($start) : null,
				'endCursor' => $page ? self::cursor($start + count($page) - 1) : null,
			],
		];
	}

	/** An opaque cursor, base64 JSON like Shopify's. */
	public static function cursor(int $offset): string
	{
		return base64_encode(json_encode(['offset' => $offset]));
	}

	private static function cursorOffset(string $cursor): int
	{
		$decoded = json_decode((string)base64_decode($cursor, true), true);
		if (!is_array($decoded) || !is_int($decoded['offset'] ?? null)) {
			throw new GraphqlRequestError("Invalid cursor for current pagination sort: $cursor");
		}
		return $decoded['offset'];
	}

	/**
	 * The fields of a selection set that apply to an object of type $type (null: every fragment),
	 * after @skip/@include and fragments.
	 *
	 * @return list<array> field nodes
	 */
	public function fields(array $selections, ?string $type, array $vars): array
	{
		return $this->collect($selections, $type, $vars);
	}

	/**
	 * The fields of a selection set that apply to an object of type $type: fragments whose type
	 * condition matches (the type itself, or an interface or union, which the schema fixture
	 * lists without members), minus @skip(if: true) and @include(if: false).
	 *
	 * @return list<array>
	 */
	private function collect(array $selections, ?string $type, array $vars): array
	{
		$out = [];
		foreach ($selections as $sel) {
			if (!$this->included($sel['directives'], $vars)) {
				continue;
			}
			if ($sel['kind'] === 'field') {
				$out[] = $sel;
				continue;
			}
			if ($sel['kind'] === 'spread') {
				$fragment = $this->fragments[$sel['name']] ?? null;
				if ($fragment === null) {
					throw new GraphqlRequestError("Fragment {$sel['name']} was used, but not defined");
				}
				[$on, $inner] = [$fragment['on'], $fragment['selections']];
			} else {
				[$on, $inner] = [$sel['on'], $sel['selections']];
			}
			if ($on === null || $type === null || $on === $type || in_array(GraphqlSchema::load()->kind($on), ['INTERFACE', 'UNION'], true)) {
				$out = array_merge($out, $this->collect($inner, $type, $vars));
			}
		}
		return $out;
	}

	/** False when @skip(if: true) or @include(if: false) drops the selection. */
	public function included(array $directives, array $vars): bool
	{
		foreach ($directives as $d) {
			$if = isset($d['args']['if']) ? (bool)self::value($d['args']['if'], $vars) : null;
			if (($d['name'] === 'skip' && $if === true) || ($d['name'] === 'include' && $if === false)) {
				return false;
			}
		}
		return true;
	}

	private function label(array $op): string
	{
		return $op['name'] === null ? "anonymous {$op['type']}" : $op['name'];
	}

	private function definition(): void
	{
		$word = $this->peek();
		if ($word === 'fragment') {
			$this->next();
			$name = (string)$this->next();
			$this->expect('on');
			$on = (string)$this->next();
			$this->directives();
			$this->fragments[$name] = ['on' => $on, 'selections' => $this->selectionSet()];
			return;
		}
		$op = ['type' => 'query', 'name' => null, 'variables' => [], 'selections' => []];
		if (in_array($word, ['query', 'mutation', 'subscription'], true)) {
			$op['type'] = (string)$this->next();
			if (!in_array($this->peek(), ['(', '{', '@'], true)) {
				$op['name'] = $this->next();
			}
			if ($this->peek() === '(') {
				$op['variables'] = $this->variableDefinitions();
			}
		}
		$this->directives();
		$op['selections'] = $this->selectionSet();
		$this->operations[] = $op;
	}

	/** @return array<string, array{type: string, default: mixed}> */
	private function variableDefinitions(): array
	{
		$out = [];
		$this->expect('(');
		while ($this->peek() !== ')') {
			$this->expect('$');
			$name = (string)$this->next();
			$this->expect(':');
			$type = $this->typeReference();
			$default = null;
			if ($this->peek() === '=') {
				$this->next();
				$default = self::value($this->valueNode(true), []);
			}
			$this->directives();
			$out[$name] = ['type' => $type, 'default' => $default];
		}
		$this->expect(')');
		return $out;
	}

	private function typeReference(): string
	{
		if ($this->peek() === '[') {
			$this->next();
			$inner = $this->typeReference();
			$this->expect(']');
			$type = "[$inner]";
		} else {
			$type = (string)$this->next();
		}
		if ($this->peek() === '!') {
			$this->next();
			$type .= '!';
		}
		return $type;
	}

	/** @return list<array> */
	private function selectionSet(): array
	{
		$out = [];
		$this->expect('{');
		while ($this->peek() !== '}') {
			if ($this->peek() === null) {
				throw new GraphqlRequestError('GraphQL parse error: unexpected end of document');
			}
			if ($this->peek() === '...') {
				$this->next();
				if ($this->peek() === 'on' || $this->peek() === '{' || $this->peek() === '@') {
					$on = null;
					if ($this->peek() === 'on') {
						$this->next();
						$on = (string)$this->next();
					}
					$out[] = ['kind' => 'inline', 'on' => $on, 'directives' => $this->directives(), 'selections' => $this->selectionSet()];
				} else {
					$out[] = ['kind' => 'spread', 'name' => (string)$this->next(), 'directives' => $this->directives()];
				}
				continue;
			}
			$alias = $name = (string)$this->next();
			if ($this->peek() === ':') {
				$this->next();
				$name = (string)$this->next();
			}
			$args = [];
			if ($this->peek() === '(') {
				$this->next();
				while ($this->peek() !== ')') {
					$arg = (string)$this->next();
					$this->expect(':');
					$args[$arg] = $this->valueNode(false);
				}
				$this->expect(')');
			}
			$directives = $this->directives();
			$selections = $this->peek() === '{' ? $this->selectionSet() : null;
			$out[] = ['kind' => 'field', 'alias' => $alias, 'name' => $name, 'args' => $args, 'directives' => $directives, 'selections' => $selections];
		}
		$this->expect('}');
		return $out;
	}

	/** @return list<array{name: string, args: array<string, array>}> */
	private function directives(): array
	{
		$out = [];
		while ($this->peek() === '@') {
			$this->next();
			$name = (string)$this->next();
			$args = [];
			if ($this->peek() === '(') {
				$this->next();
				while ($this->peek() !== ')') {
					$arg = (string)$this->next();
					$this->expect(':');
					$args[$arg] = $this->valueNode(false);
				}
				$this->expect(')');
			}
			$out[] = ['name' => $name, 'args' => $args];
		}
		return $out;
	}

	/** One value: variable, list, object, string, number, true/false/null or enum. */
	private function valueNode(bool $const): array
	{
		$kind = $this->tokens[$this->pos][0] ?? null;
		$t = $this->next();
		if ($t === null) {
			throw new GraphqlRequestError('GraphQL parse error: expected a value, got the end of the document');
		}
		if ($t === '$') {
			if ($const) {
				throw new GraphqlRequestError('GraphQL parse error: a default value cannot use a variable');
			}
			$name = (string)$this->next();
			$this->used[$name] = true;
			return ['var' => $name];
		}
		if ($t === '[') {
			$items = [];
			while ($this->peek() !== ']') {
				$items[] = $this->valueNode($const);
			}
			$this->expect(']');
			return ['list' => $items];
		}
		if ($t === '{') {
			$fields = [];
			while ($this->peek() !== '}') {
				$key = (string)$this->next();
				$this->expect(':');
				$fields[$key] = $this->valueNode($const);
			}
			$this->expect('}');
			return ['object' => $fields];
		}
		if ($kind === 'string') {
			return ['literal' => self::stringValue($t)];
		}
		if (preg_match('/^-?\d+$/', $t)) {
			return ['literal' => (int)$t];
		}
		if (is_numeric($t)) {
			return ['literal' => (float)$t];
		}
		return match ($t) {
			'true' => ['literal' => true],
			'false' => ['literal' => false],
			'null' => ['literal' => null],
			default => ['enum' => $t],
		};
	}

	/** A string token's value; block strings lose their common indentation, as GraphQL's do. */
	private static function stringValue(string $token): string
	{
		if (str_starts_with($token, '"""')) {
			$lines = explode("\n", str_replace('\\"""', '"""', substr($token, 3, -3)));
			$indent = null;
			foreach (array_slice($lines, 1) as $line) {
				if (trim($line) !== '') {
					$indent = min($indent ?? PHP_INT_MAX, strlen($line) - strlen(ltrim($line, " \t")));
				}
			}
			foreach ($lines as $i => $line) {
				$lines[$i] = $i > 0 && $indent ? substr($line, $indent) : $line;
			}
			return trim(implode("\n", $lines), "\n");
		}
		$decoded = json_decode($token);
		if (!is_string($decoded)) {
			throw new GraphqlRequestError("GraphQL parse error: bad string literal $token");
		}
		return $decoded;
	}

	private function expect(string $token): void
	{
		$got = $this->next();
		if ($got !== $token) {
			throw new GraphqlRequestError("GraphQL parse error: expected '$token', got '" . ($got ?? 'end') . "'");
		}
	}

	private function peek(): ?string
	{
		return $this->tokens[$this->pos][1] ?? null;
	}

	private function next(): ?string
	{
		return $this->tokens[$this->pos++][1] ?? null;
	}
}
