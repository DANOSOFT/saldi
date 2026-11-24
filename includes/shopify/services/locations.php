<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/locations.php --- ver 5.1.1 --- 2026.11.23
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
// The shop's Shopify locations for the lager mapping (WP04, K04): "Hent lokationer" on
// systemdata/shopify/mapping.php queues a locations.fetch job, and the worker reads the shop's
// active locations and keeps the list in the queue's runtime state (settings shopify_state),
// so the page can offer them without a Shopify call in the page request and without a table.
//
// 20261123 CL/MJ SD-865: created.

/** One page of the shop's active locations. */
const SHOPIFY_LOCATIONS_QUERY = 'query locationsFetch($after: String) { locations(first: 100, after: $after) { nodes { id name } pageInfo { hasNextPage endCursor } } }';

/** A shop has a handful of locations; more pages than this is a broken cursor, not a big shop. */
const SHOPIFY_LOCATIONS_MAX_PAGES = 20;

/** The runtime-state key the fetched list of a shop is kept under. */
function shopify_locations_state_key(int $shopId): string {
	return "locations:$shopId";
}

/**
 * Reads the shop's active locations from Shopify, page by page.
 *
 * @param array<string, mixed> $shop shopify_shops row
 * @param array<string, mixed> $opts passed on to shopify_graphql()
 * @return list<array{gid: string, name: string}>
 * @throws RuntimeException when Shopify doesn't answer; the job is tried again
 */
function shopify_locations_read(array $shop, array $opts): array {
	$locations = array();
	$after = null;
	for ($page = 0; $page < SHOPIFY_LOCATIONS_MAX_PAGES; $page++) {
		$result = shopify_graphql($shop, 'locationsFetch', SHOPIFY_LOCATIONS_QUERY, array('after' => $after), $opts);
		if (!$result['ok']) {
			throw new RuntimeException('the locations could not be read: ' . ($result['error'] ?? 'failed'));
		}
		$connection = $result['data']['locations'] ?? array();
		foreach ($connection['nodes'] ?? array() as $node) {
			$locations[] = array('gid' => (string)$node['id'], 'name' => (string)$node['name']);
		}
		if (empty($connection['pageInfo']['hasNextPage'])) {
			return $locations;
		}
		$after = (string)$connection['pageInfo']['endCursor'];
	}
	throw new RuntimeException('the locations did not end after ' . SHOPIFY_LOCATIONS_MAX_PAGES . ' pages');
}

/**
 * The locations last fetched for a shop, and when (ISO 8601, UTC); empty before the first fetch.
 *
 * @return array{fetched_at: string|null, locations: list<array{gid: string, name: string}>}
 */
function shopify_locations_fetched(int $shopId): array {
	$state = shopify_queue_state_get(shopify_locations_state_key($shopId));
	return array(
		'fetched_at' => isset($state['fetched_at']) ? (string)$state['fetched_at'] : null,
		'locations' => is_array($state['locations'] ?? null) ? $state['locations'] : array(),
	);
}

/**
 * Queues the fetch of a shop's locations. A fetch already queued or running is returned instead,
 * and a queued one, for example one waiting to retry, is brought forward to run now.
 *
 * @param array<string, mixed> $shop shopify_shops row
 * @return array{id: int, created: bool, state: string}
 */
function shopify_locations_request(array $shop): array {
	$job = shopify_queue_enqueue('locations.fetch', (int)$shop['id'], $shop['shop_key'] . ':locations', array(), SHOPIFY_QUEUE_PRIORITY['locations.fetch']);
	if (!$job['created'] && $job['state'] === 'queued') {
		shopify_queue_hasten($job['id']);
	}
	return $job;
}

/**
 * Handler of the locations.fetch job: reads the shop's locations and keeps them for mapping.php.
 *
 * @param array<string, mixed> $job claimed shopify_jobs row
 * @param array<string, mixed> $opts passed on to shopify_graphql() (test seams)
 * @return array{outcome: string}
 */
function shopify_locations_fetch_job(array $job, float $deadline, callable $heartbeat, array $opts = array()): array {
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array((int)$job['shop_id']))[0] ?? null;
	if ($shop === null) {
		return array('outcome' => 'done');
	}
	$locations = shopify_locations_read($shop, array('deadline' => $deadline, 'correlation' => array('job_id' => $job['id'] ?? null)) + $opts);
	shopify_queue_state_set(shopify_locations_state_key((int)$shop['id']), array('fetched_at' => gmdate('Y-m-d\TH:i:s\Z'), 'locations' => $locations));
	return array('outcome' => 'done');
}
