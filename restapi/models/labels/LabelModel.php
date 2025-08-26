<?php

class LabelModel
{
    // Properties to match database columns from mylabel table
    private $id;
    private $account_id;
    private $price;
    private $description;
    private $barcode;
    private $sold;
    private $created;
    private $lastprint;

    /**
     * Constructor - can create an empty Label or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing label
     */
    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->loadFromId($id);
        }
    }

    /**
     * Load label details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id)
    {
        $qtxt = "SELECT * FROM mylabel WHERE id = $id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if (db_num_rows($q) > 0) {
            $r = db_fetch_array($q);
            $this->id = (int)$r['id'];
            $this->account_id = $r['account_id'] ? (int)$r['account_id'] : null;
            $this->price = $r['price'] ? (float)$r['price'] : null;
            $this->description = $r['description'];
            $this->barcode = $r['barcode'];
            $this->sold = $r['sold'] ? (int)$r['sold'] : null;
            $this->created = $r['created'];
            $this->lastprint = $r['lastprint'];

            return true;
        }

        return false;
    }

    /**
     * Save/update the current label
     * 
     * @return bool Success status
     */
    public function save()
    {
        if ($this->id) {
            // Update existing label
            $qtxt = "UPDATE mylabel SET 
                account_id = " . ($this->account_id ? "'$this->account_id'" : "NULL") . ",
                price = " . ($this->price ? "'$this->price'" : "NULL") . ",
                description = '$this->description',
                barcode = '$this->barcode',
                sold = " . ($this->sold ? "'$this->sold'" : "NULL") . ",
                created = '$this->created',
                lastprint = '$this->lastprint'
                WHERE id = $this->id";
        } else {
            // Insert new label
            $qtxt = "INSERT INTO mylabel (
                account_id, price, description, barcode, sold, created, lastprint
            ) VALUES (
                " . ($this->account_id ? "'$this->account_id'" : "NULL") . ",
                " . ($this->price ? "'$this->price'" : "NULL") . ",
                '$this->description',
                '$this->barcode',
                " . ($this->sold ? "'$this->sold'" : "NULL") . ",
                '$this->created',
                '$this->lastprint'
            )";
        }

        $result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        $resultArray = explode("\t", $result);
        
        // Check if operation was successful
        if ($resultArray[0] == "0") {
            if (!$this->id) {
                // Insert was successful, get the inserted ID
                $qtxt = "SELECT CURRVAL(pg_get_serial_sequence('mylabel', 'id')) AS id";
                $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
                
                if ($q && ($r = db_fetch_array($q))) {
                    $this->id = (int)$r['id'];
                }
            }
            return true;
        }
        
        return false;
    }

    /**
     * Class method to get all labels
     * 
     * @param int $limit Number of records to return
     * @param string $orderBy Column to order by (default: id)
     * @param string $orderDirection Sort direction (default: DESC)
     * @param int|null $account_id Filter by account_id
     * @return LabelModel[] Array of LabelModel objects
     */
    public static function getAllItems($limit = 20, $orderBy = 'id', $orderDirection = 'DESC', $account_id = null)
    {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'account_id', 'price', 'description', 'barcode', 'created', 'lastprint'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'id';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';
        
        // Validate and sanitize limit
        $limit = (int)$limit;
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }
        
        // Build the base query
        $whereClause = "WHERE 1=1";
        
        
        // Add account_id filtering if provided
        if ($account_id) {
            $account_id = (int)$account_id;
            $whereClause .= " AND account_id = $account_id";
        }
        
        // Build the complete query
        $qtxt = "SELECT id FROM mylabel $whereClause ORDER BY $orderBy $orderDirection LIMIT $limit";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        if ($q && db_num_rows($q) > 0) {
            while ($r = db_fetch_array($q)) {
                $items[] = new LabelModel($r['id']);
            }
        }

        return $items;
    }

    /**
     * Class method to find labels by a specific field
     * 
     * @param string $field Field to search
     * @param string $value Value to match
     * @return LabelModel[] Array of matching Label objects
     */
    public static function findBy($field, $value)
    {
        // Whitelist allowed search fields
        $allowedFields = ['id', 'account_id', 'barcode', 'description'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }

        $value = pg_escape_string($value);
        $qtxt = "SELECT id FROM mylabel WHERE $field = '$value'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        if ($q && db_num_rows($q) > 0) {
            while ($r = db_fetch_array($q)) {
                $items[] = new LabelModel($r['id']);
            }
        }

        return $items;
    }

    /**
     * Method to convert object to array
     * 
     * @return array Associative array of label properties
     */
    public function toArray()
    {
        return array(
            'id' => $this->id,
            'accountId' => $this->account_id,
            'price' => $this->price,
            'description' => $this->description,
            'barcode' => $this->barcode,
            'sold' => $this->sold,
            'created' => $this->created,
            'lastPrint' => $this->lastprint
        );
    }

    // Getter methods
    public function getId() { return $this->id; }
    public function getAccountId() { return $this->account_id; }
    public function getPrice() { return $this->price; }
    public function getDescription() { return $this->description; }
    public function getBarcode() { return $this->barcode; }
    public function getSold() { return $this->sold; }
    public function getCreated() { return $this->created; }
    public function getLastprint() { return $this->lastprint; }

    // Setter methods
    public function setAccountId($account_id) { $this->account_id = $account_id; }
    public function setPrice($price) { $this->price = $price; }
    public function setDescription($description) { $this->description = $description; }
    public function setBarcode($barcode) { $this->barcode = $barcode; }
    public function setSold($sold) { $this->sold = $sold; }
    public function setCreated($created) { $this->created = $created; }
    public function setLastprint($lastprint) { $this->lastprint = $lastprint; }
}