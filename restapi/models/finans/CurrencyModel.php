<?php

class CurrencyModel 
{
    // Properties to match database columns
    private $id;
    private $description;  // beskrivelse in database
    private $currencyCode; // box1 in database
    
    /**
     * Constructor - can create an empty Currency or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing currency
     */
    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->loadFromId($id);
        }
    }
    
    /**
     * Load currency details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id)
    {
        $qtxt = "SELECT * FROM grupper WHERE id = $id AND art = 'VK'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->description = $r['beskrivelse'];
            $this->currencyCode = $r['box1'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Class method to get all currencies
     * 
     * @param string $orderBy Column to order by (default: beskrivelse)
     * @param string $orderDirection Sort direction (default: ASC)
     * @param int $limit Maximum number of results (default: 50)
     * @return CurrencyModel[] Array of Currency objects
     */
    public static function getAllItems($orderBy = 'beskrivelse', $orderDirection = 'ASC', $limit = 50)
    {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'beskrivelse', 'box1'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'beskrivelse';
        
        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';
        
        // Validate limit
        if ($limit > 200 || $limit < 1) {
            $limit = 50;
        }
        
        $qtxt = "SELECT id FROM grupper WHERE art = 'VK' ORDER BY $orderBy $orderDirection LIMIT $limit";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            $currency = new CurrencyModel($r['id']);
            if ($currency->getId()) {
                $items[] = $currency;
            }
        }
        
        return $items;
    }
    
    /**
     * Class method to find currencies by a specific field
     * 
     * @param string $field Field to search
     * @param string $value Value to match
     * @return CurrencyModel[] Array of matching Currency objects
     */
    public static function findBy($field, $value)
    {
        // Whitelist allowed search fields
        $allowedFields = ['id', 'beskrivelse', 'box1'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }
        
        $qtxt = "SELECT id FROM grupper WHERE art = 'VK' AND $field = ?";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            $currency = new CurrencyModel($r['id']);
            if ($currency->getId()) {
                $items[] = $currency;
            }
        }
        
        return $items;
    }
    
    /**
     * Method to convert object to array
     * 
     * @return array Associative array of currency properties
     */
    public function toArray()
    {
        return [
            'description' => $this->description,
            'currencyCode' => $this->currencyCode
        ];
    }
    
    // Getter methods
    public function getId() { return $this->id; }
    public function getDescription() { return $this->description; }
    public function getCurrencyCode() { return $this->currencyCode; }
    
    // Setter methods (though not used in read-only endpoint)
    public function setDescription($description) { $this->description = $description; }
    public function setCurrencyCode($currencyCode) { $this->currencyCode = $currencyCode; }
}
