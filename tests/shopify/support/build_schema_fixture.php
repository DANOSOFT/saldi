<?php
// tests/shopify/support/build_schema_fixture.php
//
// Rebuilds tests/shopify/fixtures/admin-2026-07-schema.json.gz, the compact copy
// of Shopify's Admin GraphQL schema that GraphqlSchema.php validates queries and
// payloads against. Introspection goes through Shopify's public, token-free
// proxy, so no credentials are involved. Run it when the pinned API version
// changes:
//
//   php tests/shopify/support/build_schema_fixture.php [api-version]
//
// History:
// 20261021 CL/SZ created (SD-747).

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$version = $argv[1] ?? '2026-07';
if (!preg_match('/^\d{4}-\d{2}$/', $version)) {
	fwrite(STDERR, "API version must look like 2026-07\n");
	exit(1);
}

// Seven levels covers the deepest wrapping in the schema, e.g. [[X!]!]!.
$typeRef = 'type ' . str_repeat('{ kind name ofType ', 7) . '{ kind name }' . str_repeat(' }', 7);
$query = 'query { __schema { queryType { name } mutationType { name } types { kind name '
	. 'fields(includeDeprecated: true) { name isDeprecated args { name defaultValue ' . $typeRef . ' } ' . $typeRef . ' } '
	. 'inputFields { name ' . $typeRef . ' } enumValues(includeDeprecated: true) { name isDeprecated } } } }';

$ch = curl_init('https://shopify.dev/admin-graphql-direct-proxy/' . $version);
curl_setopt_array($ch, [
	CURLOPT_POST => true,
	CURLOPT_POSTFIELDS => json_encode(['query' => $query]),
	CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 120,
]);
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
if ($raw === false || $status !== 200) {
	fwrite(STDERR, "Introspection failed: HTTP $status " . curl_error($ch) . "\n");
	exit(1);
}
try {
	$response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
	fwrite(STDERR, 'Introspection returned invalid JSON: ' . $e->getMessage() . "\n");
	exit(1);
}
// Errors next to a schema can mean a partial one (a type without its fields): never write that.
$schema = is_array($response) ? ($response['data']['__schema'] ?? null) : null;
if (!empty($response['errors']) || !is_array($schema)) {
	fwrite(STDERR, 'Introspection failed: ' . json_encode($response['errors'] ?? 'no schema in the response') . "\n");
	exit(1);
}

/** Flattens an introspection type reference to GraphQL notation, e.g. "[String!]!". */
function schema_type_string(array $type): string
{
	if ($type['kind'] === 'NON_NULL') {
		return schema_type_string($type['ofType']) . '!';
	}
	if ($type['kind'] === 'LIST') {
		return '[' . schema_type_string($type['ofType']) . ']';
	}
	return $type['name'];
}

$types = [];
foreach ($schema['types'] as $t) {
	$entry = ['kind' => $t['kind']];
	foreach ($t['fields'] ?? [] as $f) {
		$args = [];
		foreach ($f['args'] as $a) {
			// A non-null argument is only required when it has no default value.
			$args[$a['name']] = ['type' => schema_type_string($a['type']), 'hasDefault' => $a['defaultValue'] !== null];
		}
		$entry['fields'][$f['name']] = ['type' => schema_type_string($f['type']), 'args' => $args, 'deprecated' => $f['isDeprecated']];
	}
	foreach ($t['inputFields'] ?? [] as $f) {
		$entry['inputFields'][$f['name']] = schema_type_string($f['type']);
	}
	foreach ($t['enumValues'] ?? [] as $e) {
		$entry['enumValues'][$e['name']] = $e['isDeprecated'];
	}
	$types[$t['name']] = $entry;
}
ksort($types);

$out = [
	'apiVersion' => $version,
	'query' => $schema['queryType']['name'],
	'mutation' => $schema['mutationType']['name'],
	'types' => $types,
];
$target = __DIR__ . '/../fixtures/admin-' . $version . '-schema.json.gz';
if (file_put_contents($target, gzencode(json_encode($out, JSON_UNESCAPED_SLASHES), 9)) === false) {
	fwrite(STDERR, "Could not write $target\n");
	exit(1);
}
echo count($types) . " types written to $target\n";
