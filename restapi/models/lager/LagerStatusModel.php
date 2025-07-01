<?php

class LagerStatusModel {
    // Private properties matching database columns
    private $id;
    private $lager;
    private $vare_id;
    private $beholdning;
    private $lok;
    private $variant_id;

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
            $this->lager = (int)$r['lager'];
            $this->vare_id = (int)$r['vare_id'];
            $this->beholdning = (float)$r['beholdning'];
            $this->lok = $r['lok1'];
            $this->variant_id = (int)$r['variant_id'];
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
            $this->lager = (int)$r['lager'];
            $this->vare_id = (int)$r['vare_id'];
            $this->beholdning = (float)$r['beholdning'];
            $this->lok = $r['lok1'];
            $this->variant_id = (int)$r['variant_id'];
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
        if (empty($this->lager) || empty($this->vare_id) || !is_numeric($this->beholdning)) {
            throw new Exception("Lager, vare_id, and beholdning are required fields.");
        }

        // Ensure lok is a string and variant_id is numeric
        $this->lok = isset($this->lok) ? $this->lok : '';
        $this->variant_id = isset($this->variant_id) ? (int)$this->variant_id : 0;
        $this->beholdning = isset($this->beholdning) ? (float)$this->beholdning : 0.0;

        // Prepare SQL query based on whether this is an update or insert
        $this->lager = (int)$this->lager;
        $this->vare_id = (int)$this->vare_id;

        // If ID is set, we are updating; otherwise, we are inserting
        if ($this->id) {
            // Update existing record
            $qtxt = "UPDATE lagerstatus SET 
                lager = '$this->lager', 
                vare_id = '$this->vare_id', 
                beholdning = '$this->beholdning', 
                lok1 = '$this->lok', 
                variant_id = '$this->variant_id'
                WHERE id = $this->id";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            return explode("\t", $q)[0] == "0";
        } else {
            // Insert new record
            $qtxt = "INSERT INTO lagerstatus (
                lager, vare_id, beholdning, lok1, variant_id
            ) VALUES (
                '$this->lager', '$this->vare_id', '$this->beholdning', 
                '$this->lok', '$this->variant_id'
            )";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $query = db_select("SELECT id FROM lagerstatus WHERE 
                lager = '$this->lager' AND vare_id = '$this->vare_id' 
                AND lok1 = '$this->lok' AND variant_id = '$this->variant_id' 
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
        $this->beholdning = $quantity;
        return $this->save();
    }

    /**
     * Adjust inventory quantity by a specific amount
     * 
     * @param float $amount Amount to adjust (positive or negative)
     * @return bool Success status
     */
    public function adjustQuantity($amount) {
        $this->beholdning += $amount;
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
            'lager' => $this->lager,
            'vare_id' => $this->vare_id,
            'beholdning' => $this->beholdning,
            'lok' => $this->lok,
            'variant_id' => $this->variant_id
        );
    }

    // Getter methods
    public function getId() { return $this->id; }
    public function getLager() { return $this->lager; }
    public function getVareId() { return $this->vare_id; }
    public function getBeholdning() { return $this->beholdning; }
    public function getLok() { return $this->lok; }
    public function getVariantId() { return $this->variant_id; }

    // Setter methods
    public function setLager($lager) { $this->lager = $lager; }
    public function setVareId($vare_id) { $this->vare_id = $vare_id; }
    public function setBeholdning($beholdning) { $this->beholdning = $beholdning; }
    public function setLok($lok) { $this->lok = $lok; }
    public function setVariantId($variant_id) { $this->variant_id = $variant_id; }
}