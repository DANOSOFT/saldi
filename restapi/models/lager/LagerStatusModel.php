<?php

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
    // Whether the caller set the quantity; a PUT without one keeps the stored quantity (see book())
    private $quantityGiven = false;

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
     * Save/update the current lagerstatus record
     *
     * The row's locations are written here. The quantity is booked through lagerreguler()
     * (includes/std_func.php), SALDI's one way of setting stock, from what the row's batches hold.
     * Everything runs in one transaction that keeps the product row locked.
     *
     * @return bool|string True on success, false when a write failed, or a refusal message
     */
    public function save() {
        return $this->store(null);
    }

    /**
     * save() and adjustQuantity(): validate, then book inside one transaction.
     *
     * @param float|null $adjustment Added to the row's stored quantity, read under the lock; null to book $this->quantity
     * @return bool|string True on success, false when a write failed, or a refusal message
     */
    private function store($adjustment) {
        // Validate required fields
        if (!isset($this->inventory) || empty($this->productId) || !is_numeric($this->quantity)) {
            throw new Exception("Inventory, productId, and quantity are required fields.");
        }

        // Ensure location is a string and variantId is numeric
        $this->location = isset($this->location) ? $this->location : '';
        $this->location2 = isset($this->location2) ? $this->location2 : '';
        $this->location3 = isset($this->location3) ? $this->location3 : '';
        $this->location4 = isset($this->location4) ? $this->location4 : '';
        $this->location5 = isset($this->location5) ? $this->location5 : '';
        $this->variantId = isset($this->variantId) ? (int)$this->variantId : 0;
        $this->quantity = isset($this->quantity) ? (float)$this->quantity : 0.0;

        // Prepare SQL query based on whether this is an update or insert
        $this->inventory = (int)$this->inventory;
        // 20261105 CL/LH lagerreguler() books stock without a warehouse in warehouse 1, and the item card and
        //                Lagerstatus move lager 0 rows there, so the row is kept where its stock is counted.
        if ($this->inventory < 1) {
            $this->inventory = 1;
        }
        $this->productId = (int)$this->productId;
        $lok = array_map('db_escape_string', array($this->location, $this->location2, $this->location3, $this->location4, $this->location5));

        // 20261105 CL/LH The stock read and the writes lagerreguler() makes are one transaction, and the product row
        //                stays locked until it ends. Two requests for 5 and 7 both read 0 and booked 5 and 7 bought.
        transaktion('begin');
        try {
            $result = $this->book($adjustment, $lok);
        } catch (Throwable $e) {
            transaktion('rollback');
            throw $e;
        }
        if ($result !== true) {
            transaktion('rollback');
            return $result;
        }
        transaktion('commit');
        return $this->loadFromId($this->id);
    }

    /**
     * The writes of store(), inside its transaction.
     *
     * @param float|null $adjustment See store()
     * @param string[] $lok The five locations, escaped
     * @return bool|string True when booked, false when a write failed, or a refusal message
     */
    private function book($adjustment, $lok) {
        $product = db_fetch_array(db_select("SELECT id, kostpris FROM varer WHERE id = '$this->productId' FOR UPDATE", __FILE__ . " linje " . __LINE__));
        if (!$product) {
            return "Product not found.";
        }
        if ($this->variantId) {
            $qtxt = "SELECT id FROM variant_varer WHERE id = '$this->variantId' AND vare_id = '$this->productId'";
            if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                return "Variant not found for this product.";
            }
        }
        // This row's product, warehouse and variant, in lagerstatus and in the batches (includes/std_func.php)
        $place = stock_rows_condition($this->productId, $this->inventory, $this->variantId);

        // If ID is set, we are updating; otherwise, we are inserting
        if ($this->id) {
            $r = db_fetch_array(db_select("SELECT lager, vare_id, variant_id, beholdning FROM lagerstatus WHERE id = '$this->id'", __FILE__ . " linje " . __LINE__));
            if (!$r) {
                return false;
            }
            // 20261105 CL/LH The row's stock is booked for its warehouse, product and variant. A PUT that changed them
            //                left the batches behind, so the row showed stock that Lagerstatus counted somewhere else.
            if (max(1, (int)$r['lager']) != $this->inventory || (int)$r['vare_id'] != $this->productId || (int)$r['variant_id'] != $this->variantId) {
                return "The inventory, productId and variantId of an inventory status cannot be changed. Set its quantity to 0 and create one for the new combination.";
            }
            // 20261105 CL/LH lagerreguler() books the row with the lowest id. With a second row for the same place, this
            //                one was set to its batches and the other one booked, so the API answered the old quantity
            //                and the item total counted both. Nothing is booked until one row is left.
            $others = array();
            $qOthers = db_select("SELECT id FROM lagerstatus WHERE $place AND id <> '$this->id' ORDER BY id", __FILE__ . " linje " . __LINE__);
            while ($o = db_fetch_array($qOthers)) {
                $others[] = (int)$o['id'];
            }
            if ($others) {
                return "Inventory status " . implode(', ', $others) . " has the same productId, inventory and variantId. The quantity cannot be set while there is more than one inventory status for them.";
            }
            // The stored quantity is read under the lock: an adjustment adds to it, a PUT without a quantity keeps it.
            if ($adjustment !== null) {
                $this->quantity = round((float)$r['beholdning'] + $adjustment, 3);
            } elseif (!$this->quantityGiven) {
                $this->quantity = (float)$r['beholdning'];
            }
            // Update existing record; the quantity is booked below
            $qtxt = "UPDATE lagerstatus SET
                lager = '$this->inventory',
                vare_id = '$this->productId',
                lok1 = '$lok[0]',
                lok2 = '$lok[1]',
                lok3 = '$lok[2]',
                lok4 = '$lok[3]',
                lok5 = '$lok[4]',
                variant_id = '$this->variantId'
                WHERE id = '$this->id'";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        } else {
            // Check if productId and lager already have a record; under the lock, so two POSTs cannot both pass
            if (db_fetch_array(db_select("SELECT id FROM lagerstatus WHERE $place", __FILE__ . " linje " . __LINE__))) {
                return "Record with productId and inventory already exists.";
            }
            // Insert new record at 0; the quantity is booked below
            $qtxt = "INSERT INTO lagerstatus (
                lager, vare_id, beholdning, lok1, lok2, lok3, lok4, lok5, variant_id
            ) VALUES (
                '$this->inventory', '$this->productId', '0',
                '$lok[0]', '$lok[1]', '$lok[2]', '$lok[3]', '$lok[4]', '$this->variantId'
            )";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $query = db_select("SELECT id FROM lagerstatus WHERE 
                lager = '$this->inventory' AND vare_id = '$this->productId' 
                AND lok1 = '$lok[0]' AND variant_id = '$this->variantId'
                ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
            if (db_num_rows($query) > 0) {
                $r = db_fetch_array($query);
                $this->id = (int)$r['id']; // Set the new ID
            }
        }
        if (explode("\t", $q)[0] != "0" || !$this->id) {
            return false;
        }
        // 20261105 CL/LH The quantity was written to lagerstatus.beholdning only, so varer.beholdning stayed NULL,
        //                Lagerstatus, which counts batch_kob/batch_salg, showed 0, and the item card reset the row to 0.
        //                lagerreguler() books the change as bought or sold batches at the item's cost price and sets
        //                this row, the item total and a connected shop, as the item card's stock field does.
        //                It books from the row's quantity, so the row starts from what its batches hold: a row the API
        //                set before has stock no batch backs, and setting it again booked nothing or only the change.
        //                The batches counted are the ones lagerreguler() takes stock out of (stock_rows_condition()).
        $bought = db_fetch_array(db_select("SELECT coalesce(sum(antal), 0) AS antal FROM batch_kob WHERE $place", __FILE__ . " linje " . __LINE__));
        $sold = db_fetch_array(db_select("SELECT coalesce(sum(antal), 0) AS antal FROM batch_salg WHERE $place", __FILE__ . " linje " . __LINE__));
        $batchStock = round((float)$bought['antal'] - (float)$sold['antal'], 3);
        $q = db_modify("UPDATE lagerstatus SET beholdning = '$batchStock' WHERE id = '$this->id'", __FILE__ . " linje " . __LINE__);
        if (explode("\t", $q)[0] != "0") {
            return false;
        }
        lagerreguler($this->productId, $this->quantity, (float)$product['kostpris'], $this->inventory, date('Y-m-d'), $this->variantId);
        return true;
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
        $qtxt = "SELECT id FROM lagerstatus WHERE lager = '$lager_nr'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            
            $items[] = new LagerStatusModel($r['id']);
        }
        return $items;
    }

    /**
     * Update inventory quantity
     * 
     * @param float $quantity New quantity to set
     * @return bool Success status
     */
    public function updateQuantity($quantity) {
        $this->setQuantity($quantity);
        return $this->save();
    }

    /**
     * Adjust inventory quantity by a specific amount
     * 
     * The amount is added to the stored quantity as save() reads it under its lock, not to the
     * quantity this object was loaded with, so concurrent adjustments all count.
     *
     * @param float $amount Amount to adjust (positive or negative)
     * @return bool|string Success status, or a refusal message
     */
    public function adjustQuantity($amount) {
        if (!is_numeric($amount)) {
            throw new Exception("amount must be a number.");
        }
        return $this->store((float)$amount);
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
    public function setQuantity($quantity) { $this->quantity = $quantity; $this->quantityGiven = true; }
    public function setLocation($location) { $this->location = $location; }
    public function setLocation2($location2) { $this->location2 = $location2; }
    public function setLocation3($location3) { $this->location3 = $location3; }
    public function setLocation4($location4) { $this->location4 = $location4; }
    public function setLocation5($location5) { $this->location5 = $location5; }
    public function setVariantId($variantId) { $this->variantId = $variantId; }
}