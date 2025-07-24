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
     * @return bool Success status
     */
    public function save() {
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
        $this->productId = (int)$this->productId;

        // If ID is set, we are updating; otherwise, we are inserting
        if ($this->id) {
            // Update existing record
            $qtxt = "UPDATE lagerstatus SET 
                lager = '$this->inventory', 
                vare_id = '$this->productId', 
                beholdning = '$this->quantity', 
                lok1 = '$this->location',
                lok2 = '$this->location2',
                lok3 = '$this->location3',
                lok4 = '$this->location4',
                lok5 = '$this->location5',
                variant_id = '$this->variantId'
                WHERE id = $this->id";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            return explode("\t", $q)[0] == "0";
        } else {
            // Check if productId and lager already have a record
            if ($this->productId) {
                $existing = LagerStatusModel::findBy('vare_id', $this->productId);
                foreach ($existing as $item) {
                    if ($item->getInventory() == $this->inventory && $item->getVariantId() == $this->variantId) {
                        // give error message
                        return "Record with vare_id and lager already exists.";
                    }
                }
            }
            // Insert new record
            $qtxt = "INSERT INTO lagerstatus (
                lager, vare_id, beholdning, lok1, lok2, lok3, lok4, lok5, variant_id
            ) VALUES (
                '$this->inventory', '$this->productId', '$this->quantity', 
                '$this->location', '$this->location2', '$this->location3', '$this->location4', '$this->location5', '$this->variantId'
            )";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $query = db_select("SELECT id FROM lagerstatus WHERE 
                lager = '$this->inventory' AND vare_id = '$this->productId' 
                AND lok1 = '$this->location' AND variant_id = '$this->variantId' 
                ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
            if (db_num_rows($query) > 0) {
                $r = db_fetch_array($query);
                $this->id = (int)$r['id']; // Set the new ID
            }
            // If insert is successful, get the new ID
            return explode("\t", $q)[0] == "0";
        }
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
        $this->quantity = $quantity;
        return $this->save();
    }

    /**
     * Adjust inventory quantity by a specific amount
     * 
     * @param float $amount Amount to adjust (positive or negative)
     * @return bool Success status
     */
    public function adjustQuantity($amount) {
        $this->quantity += $amount;
        return $this->save();
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