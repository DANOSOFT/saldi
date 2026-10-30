<?php
include_once __DIR__."/LagerStatusModel.php";

class LagerModel
{
    // Properties to match database columns
    private $id;
    private $description;
    private $number;
    private $fiscal_year;
    private $inventory;

    // Constants for better code maintenance
    const TABLE_NAME = 'grupper';
    const ART_TYPE = 'LG';
    
    /**
     * Constructor - can create an empty Vare or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing item
     * @param int|null $kodenr Optional code number to load existing item
     * @param int|null $vare_id Optional product ID to associate with storage status
     */
    public function __construct($id = null, $kodenr = null, $vare_id = null, $nr = 1)
    {
        global $regnaar;

        // Initialize default values
        $this->id = -1;
        $this->description = "";
        $this->number = $nr;
        // 20261011 MJ SST-829 Warehouses are not tied to an accounting year - stock is keyed on
        // grupper.kodenr, and Indstillinger now saves them with fiscal_year 0. Defaulting to
        // the current year here would write a per-year row the rest of the system no longer
        // expects, and hide it from reads that no longer filter by year.
        $this->fiscal_year = 0;
        
        // Load existing data if provided
        if ($id !== null) {
            $this->loadFromId((int)$id);
        } elseif ($kodenr !== null) {
            $this->loadFromKodenr((int)$kodenr);
        }

        // Initialize inventory if vare_id is provided
        if ($vare_id !== null) {
            $this->inventory = new LagerStatusModel(null, $vare_id, $this->number);
        }
    }

    /**
     * Load item details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }
        
        $id = (int)$id; // Ensure integer type
        // 20261011 MJ SST-829 No fiscal_year filter: a warehouse saved from Indstillinger carries
        // 0, and existing tenants still have per-year copies, so filtering on the current year
        // finds neither reliably. The id identifies the row on its own.
        $qtxt = "SELECT * FROM " . self::TABLE_NAME . " WHERE id = $id AND art = '" . self::ART_TYPE . "'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        return $this->populateFromResult($q);
    }

    /**
     * Load item details from database by kodenr
     * 
     * @param int $kodenr
     * @return bool Success status
     */
    private function loadFromKodenr($kodenr)
    {
        global $regnaar;

        if (!is_numeric($kodenr)) {
            return false;
        }
        
        $kodenr = (int)$kodenr; // Ensure integer type
        // 20261011 MJ SST-829 A warehouse number identifies it on its own. Existing tenants may
        // still have one row per accounting year, so this orders the way Indstillinger and the
        // leverandørordre dropdown do - the current year first, then the newest row - and
        // populateFromResult() takes the first.
        $qtxt = "SELECT * FROM " . self::TABLE_NAME . " WHERE art = '" .
                self::ART_TYPE . "' AND kodenr = '$kodenr' " .
                "ORDER BY CASE WHEN fiscal_year = " . (int)$regnaar . " THEN 0 ELSE 1 END, " .
                "COALESCE(fiscal_year,0) DESC, id DESC";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        return $this->populateFromResult($q);
    }
    
    /**
     * Populate object properties from database result
     * 
     * @param resource $query Database query result
     * @return bool Success status
     */
    private function populateFromResult($query)
    {
        if ($r = db_fetch_array($query)) {
            // 20261008 CL/LH: these wrote $this->beskrivelse / $this->nr, which toArray() and save()
            // never read, so every warehouse came back with an empty description and number 1, and a
            // PUT reset kodenr to 1. Inventory per warehouse now looks up its own lager number.
            $this->id = (int)$r['id'];
            $this->description = $r['beskrivelse'];
            $this->number = (int)$r['kodenr'];
            $this->fiscal_year = (int)$r['fiscal_year'];
            return true;
        }
        return false;
    }

    /**
     * Sanitize data for database operations
     * 
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitize($value)
    {
        // Basic sanitization - in a real implementation, use prepared statements or your DB library's sanitization method
        return addslashes($value);
    }

    /**
     * Validate data before saving
     * 
     * @return bool|string True if valid, error message if invalid
     */
    private function validate()
    {
        if (empty($this->description)) {
            return "Description cannot be empty";
        }
        
        if (!is_numeric($this->number) || $this->number <= 0) {
            return "Number must be a positive integer";
        }
        
        if (!is_numeric($this->fiscal_year)) {
            return "Fiscal year must be numeric";
        }
        
        return true;
    }

    /**
     * Save/update the current item
     *
     * @return bool|string Success status or error message
     */
    public function save()
    {
        global $regnaar;
        
        // Validate data before saving
        $validationResult = $this->validate();
        if ($validationResult !== true) {
            return $validationResult;
        }
        
        // Sanitize text
        $description  = $this->sanitize($this->description);

        // Prepare integer fields: NULL if not numeric
        $number       = is_numeric($this->number)       ? intval($this->number)       : 'NULL';
        // 20261011 MJ SST-829 Warehouses are stored year-agnostically; falling back to the
        // current year would recreate the per-year rows this ticket stops producing.
        $fiscal_year  = is_numeric($this->fiscal_year) ? intval($this->fiscal_year) : 0;

        if ($this->id > 0) {
            // UPDATE
            $qtxt = "
              UPDATE " . self::TABLE_NAME . " SET
                beskrivelse  = '$description',
                kodenr       = $number,
                fiscal_year  = $fiscal_year
              WHERE id = {$this->id}
            ";
        } else {
            // INSERT
            $qtxt = "
              INSERT INTO " . self::TABLE_NAME . " (
                art,
                beskrivelse,
                kodenr,
                fiscal_year
              ) VALUES (
                '" . self::ART_TYPE . "',
                '$description',
                $number,
                $fiscal_year
              )
            ";
        }
        
        $q      = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        $result = explode("\t", $q)[0] === "0";

        // If insert is successful and it was a new item, update the ID
        if ($result && $this->id <= 0) {
            // Get the last inserted ID - this is database specific and might need adjustment
            $qtxt = "SELECT MAX(id) as last_id FROM " . self::TABLE_NAME;
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            if ($r = db_fetch_array($q)) {
                $this->id = (int)$r['last_id'];
            }
        }
        
        return $result;
    }

    /**
     * Delete the current item
     * 
     * @return bool Success status
     */
    public function delete()
    {
        if (!$this->id || $this->id <= 0) {
            return false;
        }

        // 20261011 MJ SST-829 The id identifies the row; filtering on the current year would
        // silently delete nothing for a warehouse stored with fiscal_year 0.
        $qtxt = "DELETE FROM " . self::TABLE_NAME . " WHERE id = $this->id AND art = '" . self::ART_TYPE . "'";
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return explode("\t", $q)[0] == "0";
    }

    /**
     * Class method to get all items
     *
     * @param int|null $vare_id Optional product ID to associate with storage status
     * @param string $orderBy Column to order by (default: kodenr)
     * @param string $orderDirection Sort direction (default: ASC)
     * @return LagerModel[] Array of LagerModel objects
     */
    public static function getAllItems($vare_id = null, $orderBy = 'kodenr', $orderDirection = 'ASC', $nr = 1)
    {
        global $regnaar;

        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'kodenr', 'beskrivelse', 'fiscal_year'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'kodenr';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        // 20261011 MJ SST-829 No fiscal_year filter, because a warehouse saved from Indstillinger
        // carries 0 and would otherwise never be listed. Existing tenants still have one row per
        // accounting year, so dropping the filter alone would return each warehouse once per
        // year; kodenr is selected as well and only the first row for each is kept. Within a
        // warehouse the rows are ordered the way Indstillinger picks the definition to edit -
        // current year first, then the newest - so the caller's own ordering still decides the
        // order of the warehouses themselves. Done in PHP rather than with DISTINCT ON, which
        // Postgres has and MySQL does not.
        $qtxt = "SELECT id, kodenr FROM " . self::TABLE_NAME . " WHERE art = '" . self::ART_TYPE .
                "' ORDER BY $orderBy $orderDirection, " .
                "CASE WHEN fiscal_year = " . (int)$regnaar . " THEN 0 ELSE 1 END, " .
                "COALESCE(fiscal_year,0) DESC, id DESC";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        $seen = [];
        while ($r = db_fetch_array($q)) {
            if (isset($seen[$r['kodenr']])) continue;
            $seen[$r['kodenr']] = true;
            $items[] = new LagerModel($r['id'], null, $vare_id, $nr);
        }

        // Create a default item if no items exist
        if (count($items) == 0) {
            $items[] = new LagerModel(null, null, $vare_id, $nr);
        }

        return $items;
    }

    /**
     * Class method to find items by a specific field
     *
     * @param string $field Field to search
     * @param string $value Value to match
     * @return LagerModel[] Array of matching objects
     */
    public static function findBy($field, $value)
    {
        global $regnaar;

        // Whitelist allowed search fields to prevent SQL injection
        $allowedFields = ['id', 'kodenr', 'beskrivelse', 'fiscal_year', 'kode'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }

        // Sanitize value to prevent SQL injection
        $value = addslashes($value);

        // 20261011 MJ SST-829 Same as getAllItems(): no fiscal_year filter, so a warehouse saved
        // with 0 is findable, and one entry per kodenr so a tenant that still has per-year rows
        // does not get the same warehouse back several times. Searching on fiscal_year itself
        // stays possible - it is in the whitelist above - and then the filter is the caller's.
        $qtxt = "SELECT id, kodenr FROM " . self::TABLE_NAME . " WHERE art = '" . self::ART_TYPE .
                "' AND $field = '$value' " .
                "ORDER BY CASE WHEN fiscal_year = " . (int)$regnaar . " THEN 0 ELSE 1 END, " .
                "COALESCE(fiscal_year,0) DESC, id DESC";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        $seen = [];
        while ($r = db_fetch_array($q)) {
            if (isset($seen[$r['kodenr']])) continue;
            $seen[$r['kodenr']] = true;
            $items[] = new LagerModel($r['id']);
        }
        return $items;
    }

    /**
     * Method to convert object to array
     *
     * @return array Associative array of item properties
     */
    public function toArray()
    {
        $data = [
            'id' => $this->id,
            'description' => $this->description,
            'number' => $this->number,
            'fiscal_year' => $this->fiscal_year,
        ];

        if (isset($this->inventory) && $this->inventory->getId() !== null) {
            $data["inventory"] = $this->inventory->toArray();
        } else {
            $data["inventory"] = null;
        }

        return $data;
    }

    // Getter methods
    public function getId()
    {
        return $this->id;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function getFiscalYear()
    {
        return $this->fiscal_year;
    }

    public function getInventory()
    {
        return $this->inventory;
    }

    // Setter methods
    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setNumber($number)
    {
        $this->number = (int)$number;
    }

    public function setFiscalYear($fiscal_year)
    {
        $this->fiscal_year = (int)$fiscal_year;
    }

    public function setInventory($inventory)
    {
        $this->inventory = $inventory;
    }
}