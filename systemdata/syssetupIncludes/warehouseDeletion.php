<?php
// --- systemdata/syssetupIncludes/warehouseDeletion.php --- 2026-10-01 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261001 CDX/PHR Delete unused warehouse definitions without rewriting inventory history.

/**
 * Delete one warehouse definition within the caller's transaction.
 * Inventory is shared between fiscal years; removing a duplicate definition must
 * never move batches, quantities, or the numbers of other warehouses.
 *
 * @return string Empty on success, otherwise the reason deletion was refused.
 */
function deleteWarehouseDefinition($groupId)
{
	$groupId = (int) $groupId;
	$group = db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE id=$groupId AND art='LG'", __FILE__ . ' linje ' . __LINE__));
	if (!$group) {
		return '';
	}
	$warehouse = (int) ifset($group, 'kodenr', 0);
	$duplicate = db_fetch_array(db_select("SELECT id FROM grupper WHERE art='LG' AND kodenr=$warehouse AND id<>$groupId LIMIT 1", __FILE__ . ' linje ' . __LINE__));
	if (!$duplicate) {
		// Historical rows remain meaningful even when their net quantity is zero.
		foreach (array('batch_kob', 'batch_salg', 'regulering', 'ordrelinjer', 'ordrer') as $table) {
			$reference = db_fetch_array(db_select("SELECT id FROM $table WHERE lager=$warehouse LIMIT 1", __FILE__ . ' linje ' . __LINE__));
			if ($reference) {
				return "Lager $warehouse har lagerhistorik eller ordrer og kan ikke slettes. Afslut lageret og flyt åbne ordrer, før lageropsætningen ændres.";
			}
		}
		$stock = db_fetch_array(db_select("SELECT id FROM lagerstatus WHERE lager=$warehouse AND beholdning<>0 LIMIT 1", __FILE__ . ' linje ' . __LINE__));
		if ($stock) {
			return "Lager $warehouse har en beholdning og kan ikke slettes.";
		}
		$department = db_fetch_array(db_select("SELECT id FROM grupper WHERE art='AFD' AND box1='$warehouse' LIMIT 1", __FILE__ . ' linje ' . __LINE__));
		if ($department) {
			return "Lager $warehouse bruges af en afdeling og kan ikke slettes. Ret afdelingens lager først.";
		}
	}
	db_modify("DELETE FROM grupper WHERE id=$groupId AND art='LG'", __FILE__ . ' linje ' . __LINE__);
	return '';
}
