<?php
// tests/shopify/ClientSchemaTest.php
//
// Every GraphQL document the client sends is checked against the stored
// 2026-07 Admin API schema, so a wrong field name fails here instead of in a
// sandbox. Also proves the checker itself catches wrong names (SD-747).
//
// History:
// 20261021 CL/SZ created (SD-747).

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/client.php';
require_once __DIR__ . '/support/GraphqlSchema.php';

final class ClientSchemaTest extends TestCase
{
	public static function clientDocuments(): array
	{
		return [
			'bulk start' => [SHOPIFY_BULK_START_MUTATION],
			'bulk recent' => [SHOPIFY_BULK_RECENT_QUERY],
			'bulk poll' => [SHOPIFY_BULK_POLL_QUERY],
		];
	}

	#[DataProvider('clientDocuments')]
	public function test_client_documents_match_the_2026_07_schema(string $document): void
	{
		$this->assertSame([], GraphqlSchema::load()->validateDocument($document));
	}

	public function test_the_pinned_version_matches_the_fixture(): void
	{
		$this->assertSame('2026-07', SHOPIFY_API_VERSION);
		$this->assertFileExists(__DIR__ . '/fixtures/admin-' . SHOPIFY_API_VERSION . '-schema.json.gz');
	}

	public function test_checker_reports_unknown_fields_arguments_types_and_deprecations(): void
	{
		$schema = GraphqlSchema::load();

		$this->assertSame(['BulkOperation has no field statuss'], $schema->validateDocument('query { bulkOperation(id: "x") { statuss } }'));
		$this->assertSame(['QueryRoot.bulkOperation has no argument gid', 'QueryRoot.bulkOperation requires argument id'],
			$schema->validateDocument('query { bulkOperation(gid: "x") { id } }'));
		$this->assertSame(['Unknown type BulkOp'], $schema->validateDocument('query q($id: ID!) { node(id: $id) { ... on BulkOp { id } } }'));
		$this->assertSame(['QueryRoot.currentBulkOperation is deprecated in this API version'],
			$schema->validateDocument('{ currentBulkOperation { id } }'));
		$this->assertSame(['Mutation has no field orderCreat'], $schema->validateDocument('mutation { orderCreat(order: {}) { order { id } } }'));
	}

	public function test_checker_accepts_aliases_fragments_variables_and_typename(): void
	{
		$doc = 'query lookup($id: ID!, $first: Int = 5) {
			a: node(id: $id) { __typename ... on Order { id name } }
			orders(first: $first, query: "source_identifier:\"pos_50:1\"") { nodes { ...o } }
		}
		fragment o on Order { id tags }';

		$this->assertSame([], GraphqlSchema::load()->validateDocument($doc));
	}

	public function test_payload_shapes_are_checked_against_input_types(): void
	{
		$schema = GraphqlSchema::load();
		$good = ['name' => 'available', 'reason' => 'correction', 'quantities' => [
			['inventoryItemId' => 'gid://shopify/InventoryItem/1', 'locationId' => 'gid://shopify/Location/1', 'quantity' => 3, 'changeFromQuantity' => null],
		]];
		// The 2026-07 compare-and-set field is changeFromQuantity; compareQuantity no longer exists.
		$bad = $good;
		$bad['quantities'][0]['compareQuantity'] = 2;
		unset($bad['reason']);

		$this->assertSame([], $schema->validateInput('InventorySetQuantitiesInput!', $good));
		$this->assertSame([
			'$.quantities[0]: InventoryQuantityInput has no field compareQuantity',
			'$: InventorySetQuantitiesInput requires reason',
		], $schema->validateInput('InventorySetQuantitiesInput!', $bad));
		$this->assertSame(['$: BULK is not a value of BulkOperationType'], $schema->validateInput('BulkOperationType', 'BULK'));
		$this->assertSame(['$: expected an enum string for BulkOperationType'], $schema->validateInput('BulkOperationType', ['QUERY']));
	}

	public function test_a_truncated_type_reference_is_a_parse_error_not_a_hang(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('incomplete type reference');
		GraphqlSchema::load()->validateDocument('query q($a: [String');
	}
}
