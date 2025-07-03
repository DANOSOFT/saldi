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

    // — new columns —
    private $notes;
    private $serienr;
    private $samlevare;
    private $delvare;
    private $min_lager;
    private $max_lager;
    private $location;
    private $gruppe;
    private $netweight;
    private $netweightunit;
    private $grossweight;
    private $grossweightunit;
    private $length;
    private $width;
    private $height;
    private $colli_webfragt;

    private $lager;
    private $gruppeObj;
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
        $notes            = pg_escape_string($this->notes            ?? '');
        $serienr          = pg_escape_string($this->serienr          ?? '');
        $samlevare        = pg_escape_string($this->samlevare        ?? '');
        $delvare          = pg_escape_string($this->delvare          ?? '');
        $min_lager        = floatval($this->min_lager        ?? 0);
        $max_lager        = floatval($this->max_lager        ?? 0);
        $location         = pg_escape_string($this->location         ?? '');
        $gruppe           = intval($this->gruppe           ?? 0);
        $netweight        = floatval($this->netweight        ?? 0);
        $netweightunit    = pg_escape_string($this->netweightunit    ?? '');
        $grossweight      = floatval($this->grossweight      ?? 0);
        $grossweightunit  = pg_escape_string($this->grossweightunit  ?? '');
        $length           = floatval($this->length           ?? 0);
        $width            = floatval($this->width            ?? 0);
        $height           = floatval($this->height           ?? 0);
        $colli_webfragt   = floatval($this->colli_webfragt   ?? 0);
        $enhed = pg_escape_string($this->enhed ?? '');
        $enhed2 = pg_escape_string($this->enhed2 ?? '');

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
        $varenr = pg_escape_string($this->varenr ?? '');
        $stregkode = pg_escape_string($this->stregkode ?? '');
        $beskrivelse = pg_escape_string($this->beskrivelse ?? '');
        $salgspris = floatval($this->salgspris ?? 0);
        $kostpris = floatval($this->kostpris ?? 0);
        $modtime = pg_escape_string($this->modtime);
        $id = intval($this->id);
        $notes           = pg_escape_string($this->notes           ?? '');
        $serienr         = pg_escape_string($this->serienr         ?? '');
        $samlevare       = pg_escape_string($this->samlevare       ?? '');
        $delvare         = pg_escape_string($this->delvare         ?? '');
        $min_lager       = floatval($this->min_lager       ?? 0);
        $max_lager       = floatval($this->max_lager       ?? 0);
        $location        = pg_escape_string($this->location        ?? '');
        $gruppe          = intval($this->gruppe          ?? 0);
        $netweight       = floatval($this->netweight       ?? 0);
        $netweightunit   = pg_escape_string($this->netweightunit   ?? '');
        $grossweight     = floatval($this->grossweight     ?? 0);
        $grossweightunit = pg_escape_string($this->grossweightunit ?? '');
        $length          = floatval($this->length          ?? 0);
        $width           = floatval($this->width           ?? 0);
        $height          = floatval($this->height          ?? 0);
        $colli_webfragt  = floatval($this->colli_webfragt  ?? 0);
        $enhed = pg_escape_string($this->enhed ?? '');
        $enhed2 = pg_escape_string($this->enhed2 ?? '');
        
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
        // — load new columns —
        $this->notes           = $row['notes']         ?? null;
        $this->serienr         = $row['serienr']       ?? null;
        $this->samlevare       = $row['samlevare']     ?? null;
        $this->delvare         = $row['delvare']       ?? null;
        $this->min_lager       = $row['min_lager']     ?? null;
        $this->max_lager       = $row['max_lager']     ?? null;
        $this->location        = $row['location']      ?? null;
        $this->gruppe          = $row['gruppe']        ?? null;
        $this->netweight       = $row['netweight']     ?? null;
        $this->netweightunit   = $row['netweightunit'] ?? null;
        $this->grossweight     = $row['grossweight']   ?? null;
        $this->grossweightunit = $row['grossweightunit'] ?? null;
        $this->length          = $row['length']        ?? null;
        $this->width           = $row['width']         ?? null;
        $this->height          = $row['height']        ?? null;
        $this->colli_webfragt  = $row['colli_webfragt']?? null;
        // …other fields…
    }

    /**
     * Convert to array
     */
    public function toArray()
    {
        return [
            'id'                => $this->id,
            'varenr'            => $this->varenr,
            'stregkode'         => $this->stregkode,
            'beskrivelse'       => $this->beskrivelse,
            'enhed'             => $this->enhed,
            'enhed2'            => $this->enhed2,
            'salgspris'         => $this->salgspris,
            'kostpris'          => $this->kostpris,
            // — new columns —
            'notes'             => $this->notes,
            'serienr'           => $this->serienr,
            'samlevare'         => $this->samlevare,
            'delvare'           => $this->delvare,
            'min_lager'         => $this->min_lager,
            'max_lager'         => $this->max_lager,
            'location'          => $this->location,
            'gruppe'            => $this->gruppe,
            'netweight'         => $this->netweight,
            'netweightunit'     => $this->netweightunit,
            'grossweight'       => $this->grossweight,
            'grossweightunit'   => $this->grossweightunit,
            'length'            => $this->length,
            'width'             => $this->width,
            'height'            => $this->height,
            'colli_webfragt'    => $this->colli_webfragt,
            // …other relations…
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

    public function setSalgspris($salgspris)
    {
        $this->salgspris = (float) $salgspris;
    }

    public function setKostpris($kostpris)
    {
        $this->kostpris = (float) $kostpris;
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
    public function setSerienr($v)         { $this->serienr         = $v; }
    public function setSamlevare($v)       { $this->samlevare       = $v; }
    public function setDelvare($v)         { $this->delvare         = $v; }
    public function setMinLager($v)        { $this->min_lager       = (float)$v; }
    public function setMaxLager($v)        { $this->max_lager       = (float)$v; }
    public function setLocation($v)        { $this->location        = $v; }
    public function setGruppe($v)          { $this->gruppe          = (int)$v; }
    public function setNetweight($v)       { $this->netweight       = (float)$v; }
    public function setNetweightunit($v)   { $this->netweightunit   = $v; }
    public function setGrossweight($v)     { $this->grossweight     = (float)$v; }
    public function setGrossweightunit($v) { $this->grossweightunit = $v; }
    public function setLength($v)          { $this->length          = (float)$v; }
    public function setWidth($v)           { $this->width           = (float)$v; }
    public function setHeight($v)          { $this->height          = (float)$v; }
    public function setColliWebfragt($v)   { $this->colli_webfragt  = (float)$v; }

    // …rest of class…
}