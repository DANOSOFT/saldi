<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/schema.php --- ver 5.0.0 --- 2026.10.21
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// Shopify data model, schema manifest version 1 (implementation plan 2.4).
// The one catalog for both the upgrade path (includes/betweenUpdates.php) and the
// fresh-tenant path (admin/opret.php). Later packages extend it only by a version bump here.
//
// 20261021 Sawaneh SD-733: created (WP01-1).
// 20261021 Sawaneh SD-733 review: a failed statement rolls its section back and keeps the marker unwritten;
//                 primary keys are repaired like the other constraints.

const SHOPIFY_SCHEMA_VERSION = 1;

final class ShopifySchemaError extends RuntimeException
{
}

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 * @var resource|\PgSql\Connection $connection
 */

/**
 * Every table of the manifest. Column DDL is PostgreSQL; the module is PostgreSQL only.
 *
 * @return array<string, array{
 *   columns: array<string, string>,
 *   primary?: string,
 *   uniques?: array<string, string>,
 *   checks?: array<string, string>,
 *   indexes?: array<string, array{cols: string, unique?: bool, where?: string}>
 * }>
 */
function shopify_schema_catalog(): array {
	$flag = 'boolean NOT NULL DEFAULT false';
	$ts = 'timestamp';
	$created = 'timestamp NOT NULL DEFAULT now()';
	return array(
		'shopify_shops' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_key' => 'text NOT NULL',
				'domain' => 'text NOT NULL',
				'navn' => 'text',
				'afd' => 'integer',
				'api_version' => "text NOT NULL DEFAULT '2026-07'",
				'is_primary' => $flag,
				'products_enabled' => $flag,
				'prices_enabled' => $flag,
				'stock_capture_enabled' => $flag,
				'stock_publish_enabled' => $flag,
				'orders_enabled' => $flag,
				'customers_enabled' => $flag,
				'store_orders_enabled' => $flag,
				'legacy_slot' => 'smallint',
				'default_freight_vare_id' => 'integer',
				'web_kasse' => 'text',
				'go_live_at' => $ts,
				'stock_baseline_at' => $ts,
				'created_at' => $created,
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_shops_shop_key_key' => 'shop_key',
				'shopify_shops_domain_key' => 'domain',
				'shopify_shops_afd_key' => 'afd',
			),
			'checks' => array(
				'shopify_shops_legacy_slot_check' => 'legacy_slot IS NULL OR legacy_slot IN (4, 5, 6)',
			),
			'indexes' => array(
				'shopify_shops_is_primary_uidx' => array('cols' => 'is_primary', 'unique' => true, 'where' => 'is_primary'),
			),
		),
		'shopify_lager' => array(
			'columns' => array(
				'lager' => 'integer NOT NULL',
				'rolle' => "text NOT NULL DEFAULT 'ingen'",
				'prioritet' => 'integer',
				'udstilling_af' => 'integer',
				'updated_at' => $created,
			),
			'primary' => 'lager',
			'checks' => array(
				'shopify_lager_rolle_check' => "rolle IN ('saelgbar', 'udstilling', 'ingen')",
			),
			'indexes' => array(
				'shopify_lager_prioritet_uidx' => array('cols' => 'prioritet', 'unique' => true, 'where' => "rolle = 'saelgbar'"),
			),
		),
		'shopify_location_map' => array(
			'columns' => array(
				'shop_id' => 'integer NOT NULL',
				'lager' => 'integer NOT NULL',
				'location_gid' => 'text NOT NULL',
				'updated_at' => $created,
			),
			'primary' => 'shop_id, lager',
			'uniques' => array(
				'shopify_location_map_location_gid_key' => 'shop_id, location_gid',
			),
		),
		'shopify_product_links' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'vare_id' => 'integer NOT NULL',
				'variant_id' => 'integer NOT NULL DEFAULT 0',
				'product_gid' => 'text',
				'variant_gid' => 'text',
				'inventory_item_gid' => 'text',
				'last_hash' => 'text',
				'listed' => 'boolean NOT NULL DEFAULT true',
				'created_at' => $created,
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_product_links_vare_key' => 'shop_id, vare_id, variant_id',
			),
			'indexes' => array(
				'shopify_product_links_variant_gid_idx' => array('cols' => 'shop_id, variant_gid'),
				'shopify_product_links_product_gid_idx' => array('cols' => 'shop_id, product_gid'),
			),
		),
		'shopify_customer_links' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'customer_gid' => 'text NOT NULL',
				'adresser_id' => 'integer NOT NULL',
				'source_updated_at' => $ts,
				'source_hash' => 'text',
				'created_at' => $created,
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_customer_links_customer_gid_key' => 'shop_id, customer_gid',
				'shopify_customer_links_adresser_id_key' => 'shop_id, adresser_id',
			),
		),
		'shopify_customer_exports' => array(
			'columns' => array(
				'shop_id' => 'integer NOT NULL',
				'adresser_id' => 'integer NOT NULL',
				'state' => "text NOT NULL DEFAULT 'pending'",
				'correlation_tag' => 'text',
				'attempt_at' => $ts,
				'created_at' => $created,
				'updated_at' => $created,
			),
			'primary' => 'shop_id, adresser_id',
			'checks' => array(
				'shopify_customer_exports_state_check' => "state IN ('pending', 'create_started', 'linked', 'parked')",
			),
		),
		'shopify_order_links' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'order_gid' => 'text',
				'draft_gid' => 'text',
				'order_name' => 'text',
				'ordre_id' => 'integer',
				'direction' => 'text NOT NULL',
				'state' => "text NOT NULL DEFAULT 'reserved'",
				'ship_lager' => 'integer',
				'flags' => "jsonb NOT NULL DEFAULT '{}'",
				'tags_b64' => 'text',
				'easyroutes_levdato' => 'date',
				'cancelled_at' => $ts,
				'created_at' => $created,
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_order_links_order_gid_key' => 'shop_id, order_gid',
				'shopify_order_links_ordre_id_key' => 'ordre_id, direction',
			),
			'checks' => array(
				'shopify_order_links_direction_check' => "direction IN ('web', 'store')",
			),
			'indexes' => array(
				'shopify_order_links_draft_gid_uidx' => array('cols' => 'shop_id, draft_gid', 'unique' => true, 'where' => 'draft_gid IS NOT NULL'),
				'shopify_order_links_state_idx' => array('cols' => 'shop_id, state'),
			),
		),
		'shopify_shipping_map' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'rate_name' => 'text NOT NULL',
				'vare_id' => 'integer',
				'pickup_lager' => 'integer',
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_shipping_map_rate_name_key' => 'shop_id, rate_name',
			),
		),
		'shopify_payment_map' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'gateway' => 'text NOT NULL',
				'card_brand' => "text NOT NULL DEFAULT ''",
				'betalingstype' => 'text',
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_payment_map_gateway_key' => 'shop_id, gateway, card_brand',
			),
		),
		'shopify_brands' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'sprog' => 'text NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_brands_sprog_key' => 'sprog',
			),
		),
		'shopify_events' => array(
			'columns' => array(
				'id' => 'bigserial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'topic' => 'text NOT NULL',
				'event_id' => 'text NOT NULL',
				'webhook_id' => 'text',
				'triggered_at' => $ts,
				'api_version' => 'text',
				'payload_b64' => 'text',
				'state' => "text NOT NULL DEFAULT 'received'",
				'received_at' => $created,
				'processed_at' => $ts,
				'job_id' => 'bigint',
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_events_event_id_key' => 'shop_id, topic, event_id',
			),
			'checks' => array(
				'shopify_events_state_check' => "state IN ('received', 'queued', 'ignored', 'error', 'anomaly')",
			),
			'indexes' => array(
				'shopify_events_state_idx' => array('cols' => 'state, received_at'),
			),
		),
		'shopify_jobs' => array(
			'columns' => array(
				'id' => 'bigserial NOT NULL',
				'type' => 'text NOT NULL',
				'shop_id' => 'integer',
				'dedupe_key' => 'text NOT NULL',
				'payload' => "jsonb NOT NULL DEFAULT '{}'",
				'priority' => 'smallint NOT NULL DEFAULT 50',
				'state' => "text NOT NULL DEFAULT 'queued'",
				'attempts' => 'integer NOT NULL DEFAULT 0',
				'max_attempts' => 'integer NOT NULL DEFAULT 52',
				'next_run_at' => $created,
				'locked_until' => $ts,
				'lock_token' => 'text',
				'schedule_slot' => 'text',
				'first_failed_at' => $ts,
				'last_error' => 'text',
				'checkpoint' => 'jsonb',
				'correlation' => "jsonb NOT NULL DEFAULT '{}'",
				'event_row_id' => 'bigint',
				'ordre_id' => 'integer',
				'order_gid' => 'text',
				'created_at' => $created,
				'updated_at' => $created,
				'finished_at' => $ts,
			),
			'primary' => 'id',
			'checks' => array(
				'shopify_jobs_priority_check' => 'priority BETWEEN 0 AND 100',
				'shopify_jobs_state_check' => "state IN ('queued', 'running', 'done', 'dead')",
			),
			'indexes' => array(
				'shopify_jobs_active_uidx' => array('cols' => 'type, dedupe_key', 'unique' => true, 'where' => "state IN ('queued', 'running')"),
				'shopify_jobs_slot_uidx' => array('cols' => 'type, dedupe_key', 'unique' => true, 'where' => 'schedule_slot IS NOT NULL'),
				'shopify_jobs_claim_idx' => array('cols' => 'state, priority, next_run_at'),
				'shopify_jobs_ordre_id_idx' => array('cols' => 'ordre_id'),
			),
		),
		'shopify_move_barriers' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'vare_id' => 'integer NOT NULL',
				'ordre_id' => 'integer',
				'created_at' => $created,
				'expires_at' => $ts,
				'state' => "text NOT NULL DEFAULT 'open'",
			),
			'primary' => 'id',
			'checks' => array(
				'shopify_move_barriers_state_check' => "state IN ('open', 'released', 'abandoned')",
			),
			'indexes' => array(
				'shopify_move_barriers_vare_idx' => array('cols' => 'vare_id, state'),
			),
		),
		'shopify_stock_dirty' => array(
			'columns' => array(
				'vare_id' => 'integer NOT NULL',
				'dirtied_at' => $created,
			),
			'primary' => 'vare_id',
		),
		'shopify_stock_state' => array(
			'columns' => array(
				'shop_id' => 'integer NOT NULL',
				'vare_id' => 'integer NOT NULL',
				'variant_id' => 'integer NOT NULL DEFAULT 0',
				'lager' => 'integer NOT NULL',
				'quantity' => 'numeric(15,3)',
				'acknowledged_at' => $ts,
			),
			'primary' => 'shop_id, vare_id, variant_id, lager',
		),
		'shopify_price_state' => array(
			'columns' => array(
				'shop_id' => 'integer NOT NULL',
				'vare_id' => 'integer NOT NULL',
				'variant_id' => 'integer NOT NULL DEFAULT 0',
				'price' => 'numeric(15,3)',
				'compare_at' => 'numeric(15,3)',
				'retail_price' => 'numeric(15,3)',
				'acknowledged_at' => $ts,
			),
			'primary' => 'shop_id, vare_id, variant_id',
		),
		'shopify_call_log' => array(
			'columns' => array(
				'id' => 'bigserial NOT NULL',
				'shop_id' => 'integer',
				'direction' => 'text NOT NULL',
				'operation' => 'text',
				'correlation' => 'text',
				'job_id' => 'bigint',
				'event_row_id' => 'bigint',
				'ordre_id' => 'integer',
				'order_gid' => 'text',
				'request_id' => 'text',
				'http_status' => 'integer',
				'cost_requested' => 'integer',
				'cost_actual' => 'integer',
				'cost_available' => 'integer',
				'cost_restore_rate' => 'integer',
				'ms' => 'integer',
				'request_b64' => 'text',
				'response_b64' => 'text',
				'created_at' => $created,
			),
			'primary' => 'id',
			'indexes' => array(
				'shopify_call_log_created_at_idx' => array('cols' => 'created_at'),
				'shopify_call_log_correlation_idx' => array('cols' => 'correlation'),
			),
		),
		'shopify_errors' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer',
				'error_key' => 'char(64) NOT NULL',
				'kind' => 'text NOT NULL',
				'ref' => 'text',
				'message' => 'text',
				'severity' => "text NOT NULL DEFAULT 'error'",
				'occurrences' => 'integer NOT NULL DEFAULT 1',
				'correlation' => "jsonb NOT NULL DEFAULT '{}'",
				'first_at' => $created,
				'last_at' => $created,
				'alarmed_at' => $ts,
				'resolved_at' => $ts,
			),
			'primary' => 'id',
			'indexes' => array(
				'shopify_errors_unresolved_uidx' => array('cols' => 'error_key', 'unique' => true, 'where' => 'resolved_at IS NULL'),
			),
		),
		'shopify_fulfillments' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'order_gid' => 'text NOT NULL',
				'fulfillment_gid' => 'text NOT NULL',
				'ordre_id' => 'integer',
				'status' => 'text',
				'tracking_company' => 'text',
				'tracking_number' => 'text',
				'tracking_url' => 'text',
				'shipped_at' => $ts,
				'payload_b64' => 'text',
				'created_at' => $created,
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_fulfillments_fulfillment_gid_key' => 'shop_id, fulfillment_gid',
			),
			'indexes' => array(
				'shopify_fulfillments_ordre_id_idx' => array('cols' => 'ordre_id'),
			),
		),
		'shopify_refunds' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'shop_id' => 'integer NOT NULL',
				'order_gid' => 'text NOT NULL',
				'refund_gid' => 'text NOT NULL',
				'ordre_id' => 'integer',
				'amount' => 'numeric(15,3)',
				'currency' => 'varchar(3)',
				'transactions_b64' => 'text',
				'state' => "text NOT NULL DEFAULT 'received'",
				'refunded_at' => $ts,
				'payload_b64' => 'text',
				'created_at' => $created,
				'updated_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_refunds_refund_gid_key' => 'shop_id, refund_gid',
			),
			'indexes' => array(
				'shopify_refunds_ordre_id_idx' => array('cols' => 'ordre_id'),
			),
		),
		'shopify_refund_allocations' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'refund_gid' => 'text NOT NULL',
				'credit_ordre_id' => 'integer NOT NULL',
				'amount_minor' => 'bigint NOT NULL',
				'created_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'shopify_refund_allocations_credit_key' => 'refund_gid, credit_ordre_id',
			),
		),
		'dokument_links' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'ordre_id' => 'integer NOT NULL',
				'doc_type' => 'text NOT NULL',
				'key_version' => 'integer NOT NULL DEFAULT 1',
				'token_hash' => 'char(64) NOT NULL',
				'pdf_path' => 'text',
				'revoked_at' => $ts,
				'created_at' => $created,
			),
			'primary' => 'id',
			'uniques' => array(
				'dokument_links_token_hash_key' => 'token_hash',
			),
			'checks' => array(
				'dokument_links_doc_type_check' => "doc_type IN ('invoice', 'credit')",
			),
			'indexes' => array(
				'dokument_links_ordre_id_idx' => array('cols' => 'ordre_id, doc_type'),
			),
		),
		'pos_leveringsvalg' => array(
			'columns' => array(
				'id' => 'serial NOT NULL',
				'navn' => 'text NOT NULL',
				'type' => 'text NOT NULL',
				'shopify_vaerdi' => 'text',
				'tags' => "jsonb NOT NULL DEFAULT '[]'",
				'aktiv' => 'boolean NOT NULL DEFAULT true',
				'sort' => 'integer NOT NULL DEFAULT 0',
			),
			'primary' => 'id',
			'checks' => array(
				'pos_leveringsvalg_type_check' => "type IN ('taget_med', 'afhentning', 'levering')",
			),
		),
	);
}

/**
 * Columns the manifest adds to legacy tables.
 *
 * @return array<string, array<string, string>> table => column => DDL type
 */
function shopify_schema_legacy_columns(): array {
	return array(
		'varer' => array(
			'oversalg' => 'varchar(2)',
		),
		'ordrer' => array(
			'leveringsvalg_id' => 'integer',
			'leveringsmaade' => 'text',
			'oensket_levdato' => 'date',
		),
		'ordrelinjer' => array(
			'retur_lager' => 'varchar(2)',
			'kredit_art' => 'varchar(6)',
		),
	);
}

function shopify_schema_is_postgres(): bool {
	global $db_type;
	return !in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true) && function_exists('pg_transaction_status');
}

/**
 * Schema version recorded on the tenant, 0 when the manifest has never completed.
 */
function shopify_schema_version_installed(): int {
	$qtxt = "SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	return $r ? (int)$r['var_value'] : 0;
}

function shopify_schema_ready(): bool {
	return shopify_schema_is_postgres() && shopify_schema_version_installed() >= SHOPIFY_SCHEMA_VERSION;
}

/**
 * Ensures the whole manifest on a PostgreSQL tenant. Every table is ensured on its own under
 * pg_advisory_xact_lock, in a savepoint when the caller already holds a transaction, and the
 * schema marker is written last, only when every table and legacy column succeeded. A failed
 * section is rolled back and makes the call return false; the other sections still run.
 * Returns false on MySQL. Once the marker shows the current version the function returns
 * without touching the catalog; $force runs the full pass anyway.
 */
function ensure_shopify_schema(bool $force = false): bool {
	global $connection;
	if (!shopify_schema_is_postgres()) {
		return false;
	}
	if (pg_transaction_status($connection) == PGSQL_TRANSACTION_INERROR) {
		return false;
	}
	if (!$force && shopify_schema_version_installed() >= SHOPIFY_SCHEMA_VERSION) {
		return true;
	}
	$ok = true;
	foreach (shopify_schema_catalog() as $table => $definition) {
		$ok = shopify_schema_locked($table, function () use ($table, $definition) {
			ensure_shopify_table($table, $definition);
		}) && $ok;
	}
	foreach (shopify_schema_legacy_columns() as $table => $columns) {
		$ok = shopify_schema_locked($table, function () use ($table, $columns) {
			foreach ($columns as $column => $type) {
				ensure_shopify_column($table, $column, $type);
			}
		}) && $ok;
	}
	if (!$ok) {
		return false;
	}
	return shopify_schema_locked('schema_version', function () {
		shopify_schema_write_marker();
	});
}

/**
 * Fresh-tenant path (admin/opret.php): the same catalog, built unconditionally.
 */
function opret_shopify_schema(): bool {
	return ensure_shopify_schema(true);
}

/**
 * Runs $op under the advisory lock for $key, in its own transaction when the connection is
 * idle and in a savepoint when the caller already holds one. A failed statement rolls the
 * section back and returns false.
 */
function shopify_schema_locked(string $key, callable $op): bool {
	global $connection;
	$ownTransaction = (pg_transaction_status($connection) == PGSQL_TRANSACTION_IDLE);
	$savepoint = 'shopify_schema_' . preg_replace('/[^a-z0-9_]/', '', strtolower($key));
	if ($ownTransaction) {
		transaktion('begin');
	} else {
		transaktion("SAVEPOINT $savepoint");
	}
	try {
		$lockKey = db_escape_string('shopify_schema:' . $key);
		shopify_schema_select("SELECT pg_advisory_xact_lock(hashtext('$lockKey'))");
		$op();
	} catch (Throwable $e) {
		if ($ownTransaction) {
			transaktion('rollback');
		} else {
			transaktion("ROLLBACK TO SAVEPOINT $savepoint");
			transaktion("RELEASE SAVEPOINT $savepoint");
		}
		if ($e instanceof ShopifySchemaError) {
			return false;
		}
		throw $e;
	}
	if ($ownTransaction) {
		transaktion('commit');
	} else {
		transaktion("RELEASE SAVEPOINT $savepoint");
	}
	return true;
}

/**
 * Runs one schema statement. Throws when db_modify() reports a failure (webservice mode
 * returns it instead of exiting) or the transaction is left aborted.
 */
function shopify_schema_exec(string $qtxt): void {
	global $connection;
	$result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	if (strpos((string)$result, '1' . chr(9)) === 0 || pg_transaction_status($connection) == PGSQL_TRANSACTION_INERROR) {
		throw new ShopifySchemaError("schema statement failed: $qtxt");
	}
}

/**
 * @return array<string, mixed>|null first row, null when there is none
 */
function shopify_schema_select(string $qtxt): ?array {
	global $connection;
	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	if (!$query || pg_transaction_status($connection) == PGSQL_TRANSACTION_INERROR) {
		throw new ShopifySchemaError("schema query failed: $qtxt");
	}
	$row = db_fetch_array($query);
	return $row ?: null;
}

/**
 * Creates the table when missing and repairs a partial one: a missing primary key, columns,
 * constraints and indexes are added, nothing existing is dropped or altered.
 *
 * @param array{columns: array<string, string>, primary?: string, uniques?: array<string, string>, checks?: array<string, string>, indexes?: array<string, array{cols: string, unique?: bool, where?: string}>} $definition
 */
function ensure_shopify_table(string $table, array $definition): void {
	$parts = array();
	foreach ($definition['columns'] as $column => $type) {
		$parts[] = "$column $type";
	}
	if (!empty($definition['primary'])) {
		$parts[] = "CONSTRAINT {$table}_pkey PRIMARY KEY ({$definition['primary']})";
	}
	foreach (($definition['uniques'] ?? array()) as $name => $columns) {
		$parts[] = "CONSTRAINT $name UNIQUE ($columns)";
	}
	foreach (($definition['checks'] ?? array()) as $name => $expression) {
		$parts[] = "CONSTRAINT $name CHECK ($expression)";
	}
	shopify_schema_exec("CREATE TABLE IF NOT EXISTS $table (" . implode(', ', $parts) . ")");
	foreach ($definition['columns'] as $column => $type) {
		ensure_shopify_column($table, $column, $type);
	}
	if (!empty($definition['primary'])) {
		ensure_shopify_primary_key($table, $definition['primary']);
	}
	foreach (($definition['uniques'] ?? array()) as $name => $columns) {
		ensure_shopify_constraint($table, $name, "UNIQUE ($columns)");
	}
	foreach (($definition['checks'] ?? array()) as $name => $expression) {
		ensure_shopify_constraint($table, $name, "CHECK ($expression)");
	}
	foreach (($definition['indexes'] ?? array()) as $name => $index) {
		ensure_shopify_index($table, $name, $index['cols'], !empty($index['unique']), $index['where'] ?? '');
	}
}

function ensure_shopify_column(string $table, string $column, string $type): void {
	shopify_schema_exec("ALTER TABLE $table ADD COLUMN IF NOT EXISTS $column $type");
}

function ensure_shopify_primary_key(string $table, string $columns): void {
	$tableEscaped = db_escape_string($table);
	if (!shopify_schema_select("SELECT 1 FROM pg_constraint WHERE contype = 'p' AND conrelid = '$tableEscaped'::regclass")) {
		shopify_schema_exec("ALTER TABLE $table ADD CONSTRAINT {$table}_pkey PRIMARY KEY ($columns)");
	}
}

function ensure_shopify_constraint(string $table, string $name, string $definition): void {
	$nameEscaped = db_escape_string($name);
	$tableEscaped = db_escape_string($table);
	if (!shopify_schema_select("SELECT 1 FROM pg_constraint WHERE conname = '$nameEscaped' AND conrelid = '$tableEscaped'::regclass")) {
		shopify_schema_exec("ALTER TABLE $table ADD CONSTRAINT $name $definition");
	}
}

function ensure_shopify_index(string $table, string $name, string $columns, bool $unique = false, string $where = ''): void {
	$qtxt = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS $name ON $table ($columns)";
	if ($where !== '') {
		$qtxt .= " WHERE $where";
	}
	shopify_schema_exec($qtxt);
}

function shopify_schema_write_marker(): void {
	$version = SHOPIFY_SCHEMA_VERSION;
	if (shopify_schema_select("SELECT id FROM settings WHERE var_grp = 'shopify' AND var_name = 'schema_version'")) {
		shopify_schema_exec("UPDATE settings SET var_value = '$version' WHERE var_grp = 'shopify' AND var_name = 'schema_version'");
	} else {
		shopify_schema_exec("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('schema_version', 'shopify', '$version', 'Shopify schema manifest version')");
	}
}

/**
 * Normalises a Shopify id to its full GID text, the only form that is stored.
 * Accepts a bare numeric id or a GID of the given resource; anything else throws.
 */
function shopify_gid(string $v, string $resource): string {
	$v = trim($v);
	if (!preg_match('/^[A-Za-z]+$/', $resource)) {
		throw new InvalidArgumentException("invalid Shopify resource: $resource");
	}
	if (preg_match('/^[0-9]+$/', $v)) {
		return "gid://shopify/$resource/$v";
	}
	if (preg_match('#^gid://shopify/([A-Za-z]+)/([0-9]+)(\?[^\s]*)?$#', $v, $m) && $m[1] === $resource) {
		return $v;
	}
	throw new InvalidArgumentException("invalid Shopify $resource id: $v");
}
