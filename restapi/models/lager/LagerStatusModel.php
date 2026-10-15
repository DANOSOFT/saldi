<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/models/lager/LagerStatusModel.php --- ver 5.0.0 --- 2026.10.06
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Route quantity writes through the stock engine and escape location fields.
// 20261001 CDX/LH Preserve legacy NULL-variant row ids and resolve quantity aliases under lock.

require_once __DIR__ . '/../../../includes/lager/stockMovement.php';
require_once __DIR__ . '/../../services/legacy/LegacyEngine.php';

class LagerStatusModel {
    // Private properties matching database columns
    private $id;
    private $inventory;
    private $productId;
    private $quantity;
    private $location;
    private $location2;
    private $location3;
    private $location4;
    private $location5;
    private $variantId;

    /**
     * Constructor - can create an empty LagerStatus or load an existing one
     *
     * @param int|null $id Optional ID to load existing record
     * @param int|null $vare_id Optional vare_id to use with lager_nr
     * @param int|null $lager_nr Optional lager number to use with vare_id
     */
    public function __construct($id = null, $vare_id = null, $lager_nr = null) {
        if ($id !== null) {
            $this->loadFromId($id);
        } elseif ($vare_id !== null && $lager_nr !== null) {
            $this->loadFromVareAndLager($vare_id, $lager_nr);
        }
    }

    /**
     * Load lagerstatus details from database by ID
     *
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id) {
        $id = (int)$id;
        $qtxt = "SELECT * FROM lagerstatus WHERE id = $id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->inventory = (int)$r['lager'];
            $this->productId = (int)$r['vare_id'];
            $this->quantity = (float)$r['beholdning'];
            $this->location = $r['lok1'];
            $this->location2 = $r['lok2'] ?? '';
            $this->location3 = $r['lok3'] ?? '';
            $this->location4 = $r['lok4'] ?? '';
            $this->location5 = $r['lok5'] ?? '';
            $this->variantId = (int)$r['variant_id'];
            return true;
        }
        return false;
    }

    /**
     * Load lagerstatus details from database by vare_id and lager number
     *
     * @param int $vare_id
     * @param int $lager_nr
     * @return bool Success status
     */
    public function loadFromVareAndLager($vare_id, $lager_nr) {
        $lager_nr = (int)$lager_nr;
        $vare_id = (int)$vare_id;
        $qtxt = "SELECT * FROM lagerstatus WHERE lager = $lager_nr AND vare_id = $vare_id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->inventory = (int)$r['lager'];
            $this->productId = (int)$r['vare_id'];
            $this->quantity = (float)$r['beholdning'];
            $this->location = $r['lok1'];
            $this->location2 = $r['lok2'] ?? '';
            $this->location3 = $r['lok3'] ?? '';
            $this->location4 = $r['lok4'] ?? '';
            $this->location5 = $r['lok5'] ?? '';
            $this->variantId = (int)$r['variant_id'];
            return true;
        }
        return false;
    }

    /**
     * Persist locations and optional quantity through the shared engine under one item lock.
     * Quantity writes run inside LegacyEngine; location-only writes need the authenticated tenant context.
     * Call inside a caller-owned transaction; synchronize the returned target after commit.
     * Relative adjustments read the current warehouse/variant row after obtaining the lock.
     *
     * @return array{saved:bool,message:?string,sync:array{vare_id:int,variant_id:int,lager:int}|null}
     */
    public function save(bool $writeQuantity = true, ?string $adjustment = null): array {
        $this->inventory = max(1, (int)$this->inventory);
        $this->productId = (int)$this->productId;
        $this->variantId = (int)$this->variantId;
        return stock_item_section([$this->productId], function () use ($writeQuantity,$adjustment): array {
            $product = db_fetch_array(db_select("SELECT kostpris FROM varer WHERE id=$this->productId", __FILE__ . ' ' . __LINE__));
            if (!$product) {
                throw new MobileT2Error('not_found', 404);
            }
            if ($this->variantId && !db_fetch_array(db_select("SELECT id FROM variant_varer WHERE id=$this->variantId AND vare_id=$this->productId", __FILE__ . ' ' . __LINE__))) {
                throw new MobileT2Error('validation_failed', 422, ['reason'=>'variant_invalid']);
            }
            $preferredId = null;
            $row = null;
            if ($this->id) {
                $original = db_fetch_array(db_select('SELECT vare_id,lager,variant_id FROM lagerstatus WHERE id=' . (int)$this->id . ' FOR UPDATE', __FILE__ . ' ' . __LINE__));
                if (!$original || (int)$original['vare_id'] !== $this->productId || max(1,(int)$original['lager']) !== $this->inventory
                    || (!$writeQuantity && (int)$original['variant_id'] !== $this->variantId)) {
                    throw new MobileT2Error('validation_failed', 422, ['reason'=>'inventory_target_changed']);
                }
                if ((int)$original['variant_id'] === $this->variantId) {
                    $preferredId = (int)$this->id;
                }
            }
            $sync = null;
            if ($writeQuantity) {
                $scope = stock_warehouse_scope($this->inventory);
                if (!$this->id && db_fetch_array(db_select("SELECT id FROM lagerstatus WHERE vare_id=$this->productId
                    AND $scope AND COALESCE(variant_id,0)=$this->variantId LIMIT 1", __FILE__ . ' ' . __LINE__))) {
                    return ['saved'=>false,'message'=>'Record with productId and inventory already exists.','sync'=>null];
                }
                $row = stock_resolve_quantity_row($this->productId, $this->variantId, $this->inventory, $preferredId);
                $before = stock_quantity((string)ifset($row,'beholdning','0'));
                $after = $adjustment === null ? stock_quantity((string)$this->quantity)
                    : stock_quantity(number_format((float)$before + (float)stock_quantity($adjustment), 3, '.', ''));
                $delta = stock_quantity(number_format((float)$after - (float)$before, 3, '.', ''));
                if ($delta !== '0') {
                    stock_log_correction($this->productId, (string)ifset($GLOBALS,'brugernavn',''), '', 'REST API', $delta);
                }
                $movement = stock_set_quantity($this->productId,$this->variantId,$this->inventory,$after,
                    stock_quantity(number_format((float)$product['kostpris'],3,'.','')),date('Y-m-d'),true);
                $sync = $movement['sync'];
                $row = db_fetch_array(db_select("SELECT id FROM lagerstatus WHERE vare_id=$this->productId
                    AND $scope AND COALESCE(variant_id,0)=$this->variantId ORDER BY id LIMIT 1", __FILE__ . ' ' . __LINE__));
            } else {
                $row = ['id'=>$preferredId];
            }
            $id = (int)ifset($row, 'id', 0);
            if (!$id) {
                throw new MobileT2Error('not_found', 404);
            }
            $locations = [];
            foreach (['location','location2','location3','location4','location5'] as $index=>$property) {
                $locations[] = 'lok' . ($index + 1) . "='" . db_escape_string((string)$this->$property) . "'";
            }
            db_modify('UPDATE lagerstatus SET ' . implode(',', $locations) . " WHERE id=$id", __FILE__ . ' ' . __LINE__);
            $this->loadFromId($id);
            return ['saved'=>true,'message'=>null,'sync'=>$sync];
        });
    }

    /**
     * Class method to get all lagerstatus records
     *
     * @param string $orderBy Column to order by (default: id)
     * @param string $orderDirection Sort direction (default: ASC)
     * @return LagerStatusModel[] Array of LagerStatus objects
     */
    public static function getAllItems($orderBy = 'id', $orderDirection = 'ASC') {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'lager', 'vare_id', 'beholdning', 'lok', 'variant_id'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'id';
        
        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';
        
        $qtxt = "SELECT id FROM lagerstatus ORDER BY $orderBy $orderDirection";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new LagerStatusModel($r['id']);
        }
        return $items;
    }

    /**
     * Class method to find lagerstatus records by a specific field
     *
     * @param string $field Field to search
     * @param string $value Value to match
     * @return LagerStatusModel[] Array of matching LagerStatus objects
     */
    public static function findBy($field, $value) {
        // Whitelist allowed search fields
        $allowedFields = ['id', 'lager', 'vare_id', 'variant_id'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }
        
        $value = (int)$value;
        $qtxt = "SELECT id FROM lagerstatus WHERE $field = '$value'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new LagerStatusModel($r['id']);
        }
        return $items;
    }

    /**
     * Get all products in a specific warehouse
     * 
     * @param int $lager_nr Warehouse number
     * @return LagerStatusModel[] Array of matching LagerStatus objects
     */
    public static function getWarehouseInventory($lager_nr) {
        $lager_nr = (int)$lager_nr;
        $qtxt = "SELECT id FROM lagerstatus WHERE lager = '$lager_nr'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            
            $items[] = new LagerStatusModel($r['id']);
        }
        return $items;
    }

    /** @return array{saved:bool,message:?string,sync:array|null} */
    public function updateQuantity($quantity): array {
        $this->quantity = $quantity;
        return $this->save();
    }

    /** @return array{saved:bool,message:?string,sync:array|null} */
    public function adjustQuantity($amount): array {
        return $this->save(true, (string)$amount);
    }

    /**
     * Method to convert object to array
     *
     * @return array Associative array of lagerstatus properties
     */
    public function toArray() {
        return array(
            'id' => $this->id,
            'inventory' => $this->inventory,
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'location' => $this->location,
            'location2' => $this->location2,
            'location3' => $this->location3,
            'location4' => $this->location4,
            'location5' => $this->location5,
            'variantId' => $this->variantId
        );
    }

    // Getter methods
    public function getId() { return $this->id; }
    public function getInventory() { return $this->inventory; }
    public function getProductId() { return $this->productId; }
    public function getQuantity() { return $this->quantity; }
    public function getLocation() { return $this->location; }
    public function getLocation2() { return $this->location2; }
    public function getLocation3() { return $this->location3; }
    public function getLocation4() { return $this->location4; }
    public function getLocation5() { return $this->location5; }
    public function getVariantId() { return $this->variantId; }

    // Setter methods
    public function setInventory($inventory) { $this->inventory = $inventory; }
    public function setProductId($productId) { $this->productId = $productId; }
    public function setQuantity($quantity) { $this->quantity = $quantity; }
    public function setLocation($location) { $this->location = $location; }
    public function setLocation2($location2) { $this->location2 = $location2; }
    public function setLocation3($location3) { $this->location3 = $location3; }
    public function setLocation4($location4) { $this->location4 = $location4; }
    public function setLocation5($location5) { $this->location5 = $location5; }
    public function setVariantId($variantId) { $this->variantId = $variantId; }
}