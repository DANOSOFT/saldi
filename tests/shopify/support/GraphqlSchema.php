<?php
// tests/shopify/support/GraphqlSchema.php
//
// Checks GraphQL documents and variable payloads against the stored Admin API
// schema (fixtures/admin-2026-07-schema.json.gz, rebuilt by
// build_schema_fixture.php), so a wrong field, argument, type or enum name
// fails in CI instead of in a sandbox. It checks names and required
// arguments, not full GraphQL validation (no variable-type compatibility).
//
// History:
// 20261021 CL/SZ created (SD-747).

final class GraphqlSchema
{
	private static ?self $cached = null;

	/** @var array<string, array> */
	private array $types;
	private string $queryRoot;
	private string $mutationRoot;
	/** @var list<array{0: string, 1: string}> */
	private array $tokens = [];
	private int $pos = 0;
	/** @var list<string> */
	private array $errors = [];

	private function __construct(array $schema)
	{
		$this->types = $schema['types'];
		$this->queryRoot = $schema['query'];
		$this->mutationRoot = $schema['mutation'];
	}

	public static function load(): self
	{
		if (self::$cached === null) {
			$raw = gzdecode((string) file_get_contents(__DIR__ . '/../fixtures/admin-2026-07-schema.json.gz'));
			self::$cached = new self(json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR));
		}
		return self::$cached;
	}

	/**
	 * Problems in a query or mutation document; empty when every name exists.
	 *
	 * @return list<string>
	 */
	public function validateDocument(string $document): array
	{
		$this->tokens = self::tokenize($document);
		$this->pos = 0;
		$this->errors = [];
		while ($this->peek() !== null) {
			$this->definition();
		}
		return $this->errors;
	}

	/**
	 * Problems in a value sent as an input type, e.g. variables for a mutation:
	 * unknown or missing input fields and unknown enum values, recursively.
	 *
	 * @return list<string>
	 */
	public function validateInput(string $typeRef, mixed $value, string $path = '$'): array
	{
		$required = str_ends_with($typeRef, '!');
		$inner = $required ? substr($typeRef, 0, -1) : $typeRef;
		if ($value === null) {
			return $required ? ["$path: required $typeRef is null"] : [];
		}
		if (str_starts_with($inner, '[')) {
			if (!is_array($value) || !array_is_list($value)) {
				return ["$path: expected a list for $inner"];
			}
			$errors = [];
			foreach ($value as $i => $item) {
				$errors = array_merge($errors, $this->validateInput(substr($inner, 1, -1), $item, "{$path}[$i]"));
			}
			return $errors;
		}
		$type = $this->types[$inner] ?? null;
		if ($type === null) {
			return ["$path: unknown type $inner"];
		}
		if ($type['kind'] === 'ENUM') {
			if (!is_string($value)) {
				return ["$path: expected an enum string for $inner"];
			}
			return isset($type['enumValues'][$value]) ? [] : ["$path: $value is not a value of $inner"];
		}
		if ($type['kind'] !== 'INPUT_OBJECT') {
			return [];
		}
		if (!is_array($value)) {
			return ["$path: expected an object for $inner"];
		}
		$errors = [];
		foreach ($value as $key => $item) {
			if (!isset($type['inputFields'][$key])) {
				$errors[] = "$path: $inner has no field $key";
				continue;
			}
			$errors = array_merge($errors, $this->validateInput($type['inputFields'][$key], $item, "$path.$key"));
		}
		foreach ($type['inputFields'] as $key => $fieldType) {
			if (str_ends_with($fieldType, '!') && !array_key_exists($key, $value)) {
				$errors[] = "$path: $inner requires $key";
			}
		}
		return $errors;
	}

	private function definition(): void
	{
		$word = $this->peek();
		if ($word === 'fragment') {
			$this->next();
			$this->next();
			$this->expect('on');
			$this->selectionSet($this->typeName($this->next()));
			return;
		}
		$root = $this->queryRoot;
		if ($word === 'mutation' || $word === 'query' || $word === 'subscription') {
			$this->next();
			$root = $word === 'mutation' ? $this->mutationRoot : $this->queryRoot;
			if ($this->peek() !== '(' && $this->peek() !== '{' && $this->peek() !== '@') {
				$this->next();
			}
			if ($this->peek() === '(') {
				$this->variableDefinitions();
			}
		}
		$this->skipDirectives();
		$this->selectionSet($root);
	}

	private function variableDefinitions(): void
	{
		$this->expect('(');
		while ($this->peek() !== ')') {
			$this->expect('$');
			$this->next();
			$this->expect(':');
			$type = $this->typeReference();
			$this->typeName(trim($type, '[]!'));
			if ($this->peek() === '=') {
				$this->next();
				$this->skipValue();
			}
		}
		$this->expect(')');
	}

	private function typeReference(): string
	{
		$out = '';
		$depth = 0;
		while (true) {
			if ($this->peek() === null) {
				throw new RuntimeException('GraphQL parse error: incomplete type reference');
			}
			$t = (string) $this->next();
			$out .= $t;
			if ($t === '[') {
				$depth++;
				continue;
			}
			if ($t === ']') {
				$depth--;
			}
			if ($this->peek() === '!') {
				$out .= $this->next();
			}
			if ($depth <= 0) {
				return $out;
			}
		}
	}

	private function selectionSet(?string $typeName): void
	{
		$this->expect('{');
		while ($this->peek() !== '}' && $this->peek() !== null) {
			if ($this->peek() === '...') {
				$this->next();
				if ($this->peek() === 'on') {
					$this->next();
					$this->selectionSet($this->typeName($this->next()));
				} else {
					$this->next();
				}
				continue;
			}
			$this->field($typeName);
		}
		$this->expect('}');
	}

	private function field(?string $parent): void
	{
		$name = $this->next();
		if ($this->peek() === ':') {
			$this->next();
			$name = $this->next();
		}
		$field = null;
		if ($parent !== null && $name !== '__typename') {
			$field = $this->types[$parent]['fields'][$name] ?? null;
			if ($field === null) {
				$this->errors[] = "$parent has no field $name";
			} elseif ($field['deprecated']) {
				$this->errors[] = "$parent.$name is deprecated in this API version";
			}
		}
		$given = [];
		if ($this->peek() === '(') {
			$this->next();
			while ($this->peek() !== ')') {
				$arg = $this->next();
				$given[] = $arg;
				$this->expect(':');
				$this->skipValue();
				if ($field !== null && !isset($field['args'][$arg])) {
					$this->errors[] = "$parent.$name has no argument $arg";
				}
			}
			$this->expect(')');
		}
		foreach ($field['args'] ?? [] as $arg => $spec) {
			if (str_ends_with($spec['type'], '!') && !$spec['hasDefault'] && !in_array($arg, $given, true)) {
				$this->errors[] = "$parent.$name requires argument $arg";
			}
		}
		$this->skipDirectives();
		if ($this->peek() === '{') {
			$this->selectionSet($field === null ? null : trim($field['type'], '[]!'));
		}
	}

	private function typeName(string $name): ?string
	{
		if (!isset($this->types[$name])) {
			$this->errors[] = "Unknown type $name";
			return null;
		}
		return $name;
	}

	private function skipDirectives(): void
	{
		while ($this->peek() === '@') {
			$this->next();
			$this->next();
			if ($this->peek() === '(') {
				$this->skipValue();
			}
		}
	}

	/** Skips one value (literal, variable, list, object) or a balanced (...) group. */
	private function skipValue(): void
	{
		$open = $this->next();
		if ($open === '$') {
			$this->next();
			return;
		}
		$close = ['[' => ']', '{' => '}', '(' => ')'][$open] ?? null;
		if ($close === null) {
			return;
		}
		$depth = 1;
		while ($depth > 0 && $this->peek() !== null) {
			$t = $this->next();
			if ($t === $open) {
				$depth++;
			} elseif ($t === $close) {
				$depth--;
			}
		}
	}

	private function expect(string $token): void
	{
		$got = $this->next();
		if ($got !== $token) {
			throw new RuntimeException("GraphQL parse error: expected '$token', got '" . ($got ?? 'end') . "'");
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

	/** @return list<array{0: string, 1: string}> kind and text; commas, whitespace and comments dropped. */
	private static function tokenize(string $doc): array
	{
		preg_match_all('/"""(?:[^"\\\\]|\\\\.|"(?!""))*"""|"(?:[^"\\\\]|\\\\.)*"|\.\.\.|[!$():=@\[\]{}|]|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|[_A-Za-z][_0-9A-Za-z]*|#[^\n]*|[\s,]+|./s', $doc, $m);
		$tokens = [];
		foreach ($m[0] as $t) {
			if (trim($t, " \t\r\n,") === '' || $t[0] === '#') {
				continue;
			}
			$tokens[] = [$t[0] === '"' ? 'string' : 'token', $t];
		}
		return $tokens;
	}
}
