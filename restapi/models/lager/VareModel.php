<?php

include_once __DIR__ . "/VareGruppeModel.php";
include_once __DIR__ . "/LagerModel.php";
include_once __DIR__ . "/SizeModel.php";
include_once __DIR__ . "/VareReadDTO.php";

class VareModel
{
    // Properties to match database columns
    private $id;
    private $varenr;
    private $stregkode;
    private $beskrivelse;
    private $enhed;
    private $enhed2;
    private $salgspris;
    private $kostpris;
    private $lager;
    private $gruppe;
    
    // New size object property
    private $size;

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
    public static function getAllItems($orderBy = 'id', $orderDirection = 'ASC')
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
        
        $query = "SELECT * FROM varer ORDER BY $orderBy $orderDirection";
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
        $varenr = pg_escape_string($this->varenr ?? '');
        $stregkode = pg_escape_string($this->stregkode ?? '');
        $beskrivelse = pg_escape_string($this->beskrivelse ?? '');
        $salgspris = floatval($this->salgspris ?? 0);
        $kostpris = floatval($this->kostpris ?? 0);
        $modtime = pg_escape_string($this->modtime);
        
        $query = "INSERT INTO varer (varenr, stregkode, beskrivelse, salgspris, kostpris, modtime) 
                  VALUES ('$varenr', '$stregkode', '$beskrivelse', $salgspris, $kostpris, '$modtime')";
        
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
        $varenr = pg_escape_string($this->varenr ?? '');
        $stregkode = pg_escape_string($this->stregkode ?? '');
        $beskrivelse = pg_escape_string($this->beskrivelse ?? '');
        $salgspris = floatval($this->salgspris ?? 0);
        $kostpris = floatval($this->kostpris ?? 0);
        $modtime = pg_escape_string($this->modtime);
        $id = intval($this->id);
        
        $query = "UPDATE varer SET 
                    varenr = '$varenr', 
                    stregkode = '$stregkode', 
                    beskrivelse = '$beskrivelse', 
                    salgspris = $salgspris, 
                    kostpris = $kostpris, 
                    modtime = '$modtime' 
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
            if ($this->varenr) {
                $varenr = pg_escape_string($this->varenr);
                $query = "SELECT id FROM varer WHERE varenr = '$varenr' ORDER BY id DESC LIMIT 1";
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
        $this->varenr = $row['varenr'] ?? null;
        $this->stregkode = $row['stregkode'] ?? null;
        $this->beskrivelse = $row['beskrivelse'] ?? null;
        $this->enhed = $row['enhed'] ?? null;
        $this->enhed2 = $row['enhed2'] ?? null;
        $this->salgspris = $row['salgspris'] ?? null;
        $this->kostpris = $row['kostpris'] ?? null;
        $this->modtime = $row['modtime'] ?? null;
    }

    /**
     * Convert to array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'varenr' => $this->varenr,
            'stregkode' => $this->stregkode,
            'beskrivelse' => $this->beskrivelse,
            'enhed' => $this->enhed,
            'enhed2' => $this->enhed2,
            'salgspris' => $this->salgspris,
            'kostpris' => $this->kostpris,
            'gruppe' => $this->gruppe ? $this->gruppe->toArray() : null,
            'lager' => $this->lager ? $this->lager->toArray() : null,
            'size' => $this->size ? $this->size->toArray() : null,
            'modtime' => $this->modtime
        ];
    }

    // Getter methods
    public function getId()
    {
        return $this->id;
    }
    public function getVarenr()
    {
        return $this->varenr;
    }
    public function getStregkode()
    {
        return $this->stregkode;
    }
    public function getBeskrivelse()
    {
        return $this->beskrivelse;
    }
    public function getSalgsPris()
    {
        return $this->salgspris;
    }
    public function getPrisInklMoms()
    {
        if ($this->gruppe->getMomsFri() || $this->gruppe->getSellAccount() === null) {
            return $this->salgspris;
        }

        $vatRate = $this->gruppe->getSellAccount()->getMoms()->getSats();
        $priceWithVat = $this->salgspris * (1 + ($vatRate / 100));
        
        return $priceWithVat;
    }
    public function getKostPris()
    {
        return $this->kostpris;
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
    public function setVarenr($varenr)
    {
        $this->varenr = $varenr;
    }
    public function setStregkode($stregkode)
    {
        $this->stregkode = $stregkode;
    }
    public function setBeskrivelse($beskrivelse)
    {
        $this->beskrivelse = $beskrivelse;
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
}