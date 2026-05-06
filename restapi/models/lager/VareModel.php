<?php

include_once __DIR__ . "/VareGruppeModel.php";
include_once __DIR__ . "/LagerModel.php";
include_once __DIR__ . "/SizeModel.php";
include_once __DIR__ . "/VareReadDTO.php";

class VareModel
{
    // Properties to match database columns
    private $id;
    private $sku;
    private $barcode;
    private $description;
    private $unit;
    private $unit2;
    private $salesPrice;
    private $costPrice;

    // — new columns —
    private $notes;
    private $serialNumber;
    private $collectionOfItems;
    private $partialItem;
    private $minInventory;
    private $maxInventory;
    private $location;
    private $group;
    private $netweight;
    private $netweightunit;
    private $grossweight;
    private $grossweightunit;
    private $length;
    private $width;
    private $height;
    private $colli_webfreight;

    private $inventory;
    private $groupObject;
    private $size;
    private $modtime;

    /**
     * Constructor - can create an empty Vare or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing item
     */
    public function __construct($id = null)
    {
        // Initialize empty size object
        $this->size = new SizeModel();
        
        if ($id !== null) {
            $this->loadFromId($id);
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
        $query = "SELECT * FROM varer WHERE id = '$id'";
        $result = db_select($query, __FILE__ . " line " . __LINE__);
        
        if ($result && db_num_rows($result) > 0) {
            $row = db_fetch_array($result);
            if ($row) {
                $this->loadFromArray($row);
            }
        }
    }

    /**
     * Find products by field value
     */
    public static function findBy($field, $value)
    {
        // Validate field name to prevent SQL injection
        $allowedFields = ['id', 'varenr', 'stregkode', 'beskrivelse'];
        if (!in_array($field, $allowedFields)) {
            error_log("Invalid field name in VareModel::findBy: $field");
            return [];
        }
        
        // Escape the value to prevent SQL injection
        $escapedValue = pg_escape_string($value);
        $query = "SELECT * FROM varer WHERE $field = '$escapedValue'";
        
        // Execute query with error handling
        $result = db_select($query, __FILE__ . " line " . __LINE__);
        
        // Check if query failed
        if ($result === false) {
            error_log("Database query failed in VareModel::findBy for field: $field, value: $value");
            return [];
        }
        
        $items = [];
        if ($result && db_num_rows($result) > 0) {
            while ($row = db_fetch_array($result)) {
                if ($row) {
                    $vare = new VareModel();
                    $vare->loadFromArray($row);
                    $items[] = $vare;
                }
            }
        }
        
        return $items;
    }

    /**
     * Get all products
     */
    public static function getAllItems($orderBy = 'id', $orderDirection = 'ASC', $limit)
    {
        // Validate orderBy to prevent SQL injection
        $allowedOrderBy = ['id', 'varenr', 'beskrivelse', 'modtime'];
        $allowedDirection = ['ASC', 'DESC'];
        
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'id';
        }
        if (!in_array($orderDirection, $allowedDirection)) {
            $orderDirection = 'ASC';
        }
        
        $query = "SELECT * FROM varer ORDER BY $orderBy $orderDirection LIMIT $limit";
        $result = db_select($query, __FILE__ . " line " . __LINE__);
        
        $items = [];
        if ($result && db_num_rows($result) > 0) {
            while ($row = db_fetch_array($result)) {
                if ($row) {
                    $vare = new VareModel();
                    $vare->loadFromArray($row);
                    $items[] = $vare;
                }
            }
        }
        
        return $items;
    }

    /**
     * Save product to database
     */
    public function save()
    {
        try {
            if ($this->id) {
                // UPDATE existing record
                return $this->updateRecord();
            } else {
                // INSERT new record
                // check if sku already exists
                if ($this->sku) {
                    $existing = self::findBy('varenr', $this->sku);
                    if (count($existing) > 0) {
                        throw new Exception("Product with SKU '{$this->sku}' already exists.");
                    }
                }
                return $this->insertRecord();
            }
        } catch (Exception $e) {
            error_log("Error in VareModel::save(): " . $e->getMessage());
            return "Error saving product: " . $e->getMessage();
        }
    }

    /**
     * Insert new record
     */
    private function insertRecord()
    {
        $this->modtime = date('Y-m-d H:i:s');
        
        // Escape values to prevent SQL injection
        $varenr = pg_escape_string($this->sku ?? '');
        $stregkode = pg_escape_string($this->barcode ?? '');
        $beskrivelse = pg_escape_string($this->description ?? '');
        $salgspris = floatval($this->salesPrice ?? 0);
        $kostpris = floatval($this->costPrice ?? 0);
        $modtime = pg_escape_string($this->modtime);
        $notes            = pg_escape_string($this->notes            ?? '');
        $serienr          = pg_escape_string($this->serialNumber          ?? '');
        $samlevare        = pg_escape_string($this->collectionOfItems        ?? '');
        $delvare          = pg_escape_string($this->partialItem          ?? '');
        $min_lager        = floatval($this->minInventory        ?? 0);
        $max_lager        = floatval($this->maxInventory        ?? 0);
        $location         = pg_escape_string($this->location         ?? '');
        $gruppe           = intval($this->group           ?? 0);
        $netweight        = floatval($this->netweight        ?? 0);
        $netweightunit    = pg_escape_string($this->netweightunit    ?? '');
        $grossweight      = floatval($this->grossweight      ?? 0);
        $grossweightunit  = pg_escape_string($this->grossweightunit  ?? '');
        $length           = floatval($this->length           ?? 0);
        $width            = floatval($this->width            ?? 0);
        $height           = floatval($this->height           ?? 0);
        $colli_webfragt   = floatval($this->colli_webfreight   ?? 0);
        $enhed = pg_escape_string($this->unit ?? '');
        $enhed2 = pg_escape_string($this->unit2 ?? '');

        $query = "INSERT INTO varer (
                    varenr, stregkode, beskrivelse, enhed, enhed2,
                    salgspris, kostpris, notes, serienr, samlevare,
                    delvare, min_lager, max_lager, location, gruppe,
                    netweight, netweightunit, grossweight, grossweightunit,
                    length, width, height, colli_webfragt, modtime
                  ) VALUES (
                    '$varenr','$stregkode','$beskrivelse','$enhed','$enhed2',
                     $salgspris,  $kostpris,'$notes','$serienr','$samlevare',
                     '$delvare',  $min_lager,  $max_lager,'$location',$gruppe,
                     $netweight,'$netweightunit',$grossweight,'$grossweightunit',
                     $length,$width,$height,$colli_webfragt,'$modtime'
                  )";
        
        $result = db_modify($query, __FILE__ . " line " . __LINE__);
        
        if ($result) {
            // Try to get the inserted ID
            $this->loadLastInsertedId();
            return true;
        }
        
        return false;
    }

    /**
     * Update existing record
     */
    private function updateRecord()
    {
        $this->modtime = date('Y-m-d H:i:s');
        
        // Escape values to prevent SQL injection
        $varenr = pg_escape_string($this->sku ?? '');
        $stregkode = pg_escape_string($this->barcode ?? '');
        $beskrivelse = pg_escape_string($this->description ?? '');
        $salgspris = floatval($this->salesPrice ?? 0);
        $kostpris = floatval($this->costPrice ?? 0);
        $modtime = pg_escape_string($this->modtime);
        $id = intval($this->id);
        $notes           = pg_escape_string($this->notes           ?? '');
        $serienr         = pg_escape_string($this->serialNumber         ?? '');
        $samlevare       = pg_escape_string($this->collectionOfItems       ?? '');
        $delvare         = pg_escape_string($this->partialItem         ?? '');
        $min_lager       = floatval($this->minInventory       ?? 0);
        $max_lager       = floatval($this->maxInventory       ?? 0);
        $location        = pg_escape_string($this->location        ?? '');
        $gruppe          = intval($this->group          ?? 0);
        $netweight       = floatval($this->netweight       ?? 0);
        $netweightunit   = pg_escape_string($this->netweightunit   ?? '');
        $grossweight     = floatval($this->grossweight     ?? 0);
        $grossweightunit = pg_escape_string($this->grossweightunit ?? '');
        $length          = floatval($this->length          ?? 0);
        $width           = floatval($this->width           ?? 0);
        $height          = floatval($this->height          ?? 0);
        $colli_webfragt  = floatval($this->colli_webfreight  ?? 0);
        $enhed = pg_escape_string($this->unit ?? '');
        $enhed2 = pg_escape_string($this->unit2 ?? '');

        $query = "UPDATE varer SET
                    varenr          = '$varenr',
                    stregkode       = '$stregkode',
                    beskrivelse     = '$beskrivelse',
                    enhed           = '$enhed',
                    enhed2          = '$enhed2',
                    salgspris       = $salgspris,
                    kostpris        = $kostpris,
                    notes           = '$notes',
                    serienr         = '$serienr',
                    samlevare       = '$samlevare',
                    delvare         = '$delvare',
                    min_lager       = $min_lager,
                    max_lager       = $max_lager,
                    location        = '$location',
                    gruppe          = $gruppe,
                    netweight       = $netweight,
                    netweightunit   = '$netweightunit',
                    grossweight     = $grossweight,
                    grossweightunit = '$grossweightunit',
                    length          = $length,
                    width           = $width,
                    height          = $height,
                    colli_webfragt  = $colli_webfragt,
                    modtime         = '$modtime'
                  WHERE id = $id";
        
        $result = db_modify($query, __FILE__ . " line " . __LINE__);
        
        return $result !== false;
    }

    /**
     * Get the last inserted ID
     */
    private function loadLastInsertedId()
    {
        try {
            if ($this->sku) {
                $sku = pg_escape_string($this->sku);
                $query = "SELECT id FROM varer WHERE varenr = '$sku' ORDER BY id DESC LIMIT 1";
                $result = db_select($query, __FILE__ . " line " . __LINE__);
                
                if ($result && db_num_rows($result) > 0) {
                    $row = db_fetch_array($result);
                    if ($row && isset($row['id'])) {
                        $this->id = $row['id'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting last inserted ID: " . $e->getMessage());
        }
    }

    /**
     * Delete product from database
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }
        
        $id = intval($this->id);
        $query = "DELETE FROM varer WHERE id = $id";
        $result = db_modify($query, __FILE__ . " line " . __LINE__);
        
        if ($result !== false) {
            $this->id = null;
            return true;
        }
        
        return false;
    }

    /**
     * Load product data from array
     */
    private function loadFromArray($row)
    {
        $this->id = $row['id'] ?? null;
        $this->sku = $row['varenr'] ?? null;
        $this->barcode = $row['stregkode'] ?? null;
        $this->description = $row['beskrivelse'] ?? null;
        $this->unit = $row['enhed'] ?? null;
        $this->unit2 = $row['enhed2'] ?? null;
        $this->salesPrice = $row['salgspris'] ?? null;
        $this->costPrice = $row['kostpris'] ?? null;
        // — load new columns —
        $this->notes           = $row['notes']         ?? null;
        $this->serialNumber         = $row['serienr']       ?? null;
        $this->collectionOfItems       = $row['samlevare']     ?? null;
        $this->partialItem         = $row['delvare']       ?? null;
        $this->minInventory       = $row['min_lager']     ?? null;
        $this->maxInventory       = $row['max_lager']     ?? null;
        $this->location        = $row['location']      ?? null;
        $this->group          = $row['gruppe']        ?? null;
        $this->netweight       = $row['netweight']     ?? null;
        $this->netweightunit   = $row['netweightunit'] ?? null;
        $this->grossweight     = $row['grossweight']   ?? null;
        $this->grossweightunit = $row['grossweightunit'] ?? null;
        $this->length          = $row['length']        ?? null;
        $this->width           = $row['width']         ?? null;
        $this->height          = $row['height']        ?? null;
        $this->colli_webfreight  = $row['colli_webfragt']?? null;
        // …other fields…
    }

    /**
     * Convert to array
     */
    public function toArray()
    {
        return [
            'id'                => $this->id,
            'sku'               => $this->sku,
            'barcode'           => $this->barcode,
            'description'       => $this->description,
            'unit'              => $this->unit,
            'unit2'             => $this->unit2,
            'salesPrice'        => $this->salesPrice,
            'costPrice'         => $this->costPrice,
            // — new columns —
            'notes'             => $this->notes,
            'serialNumber'      => $this->serialNumber,
            'collectionOfItems' => $this->collectionOfItems,
            'partialItem'       => $this->partialItem,
            'minInventory'      => $this->minInventory,
            'maxInventory'      => $this->maxInventory,
            'location'          => $this->location,
            'group'             => $this->group,
            'netweight'         => $this->netweight,
            'netweightunit'     => $this->netweightunit,
            'grossweight'       => $this->grossweight,
            'grossweightunit'   => $this->grossweightunit,
            'length'            => $this->length,
            'width'             => $this->width,
            'height'            => $this->height,
            'colli_webfreight'  => $this->colli_webfreight,
            // …other relations…
        ];
    }

    // Getter methods
    public function getId()
    {
        return $this->id;
    }
    public function getSku()
    {
        return $this->sku;
    }
    public function getBarcode()
    {
        return $this->barcode;
    }
    public function getDescription()
    {
        return $this->description;
    }
    public function getSalesPrice()
    {
        return $this->salesPrice;
    }
    public function getPrisInklMoms()
    {
        if ($this->group->getMomsFri() || $this->group->getSellAccount() === null) {
            return $this->salesPrice;
        }

        $vatRate = $this->group->getSellAccount()->getMoms()->getSats();
        $priceWithVat = $this->salesPrice * (1 + ($vatRate / 100));
        
        return $priceWithVat;
    }
    public function getCostPrice()
    {
        return $this->costPrice;
    }

    /**
     * Get size object
     * 
     * @return SizeModel
     */
    public function getSize()
    {
        return $this->size;
    }

    // Setter methods
    public function setSku($sku)
    {
        $this->sku = $sku;
    }

    public function setBarcode($barcode)
    {
        $this->barcode = $barcode;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setSalesPrice($salesPrice)
    {
        $this->salesPrice = (float) $salesPrice;
    }

    public function setCostPrice($costPrice)
    {
        $this->costPrice = (float) $costPrice;
    }

    /**
     * Set size object
     * 
     * @param SizeModel $size
     * @return VareModel
     */
    public function setSize(SizeModel $size)
    {
        $this->size = $size;
        return $this;
    }

    // — add getters/setters for each new prop —
    public function setNotes($v)           { $this->notes           = $v; }
    public function setSerialNumber($v)    { $this->serialNumber    = $v; }
    public function setCollectionOfItems($v){ $this->collectionOfItems = $v; }
    public function setPartialItem($v)     { $this->partialItem     = $v; }
    public function setMinInventory($v)    { $this->minInventory    = (float)$v; }
    public function setMaxInventory($v)    { $this->maxInventory    = (float)$v; }
    public function setLocation($v)        { $this->location        = $v; }
    public function setGroup($v)          { $this->group          = (int)$v; }
    public function setNetweight($v)       { $this->netweight       = (float)$v; }
    public function setNetweightunit($v)   { $this->netweightunit   = $v; }
    public function setGrossweight($v)     { $this->grossweight     = (float)$v; }
    public function setGrossweightunit($v) { $this->grossweightunit = $v; }
    public function setLength($v)          { $this->length          = (float)$v; }
    public function setWidth($v)           { $this->width           = (float)$v; }
    public function setHeight($v)          { $this->height          = (float)$v; }
    public function setColliWebfreight($v) { $this->colli_webfreight = (float)$v; }
    public function setUnit($v)            { $this->unit            = $v; }
    public function setUnit2($v)           { $this->unit2           = $v; }
}