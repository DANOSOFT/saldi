<?php

class VatModel
{
    // Properties to match database columns
    private $id;
    private $beskrivelse;
    private $momskode;
    private $nr;
    private $fiscal_year;

    # Values
    private $account;
    private $sats;
    private $modkonto;
    private $map;

    /**
     * Constructor - can create an empty Vare or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing item
     */
    public function __construct($id = null, $vatcode = null)
    {
        if ($id !== null) {
            $this->loadFromId($id);
        }
        if ($vatcode !== null) {
            $this->loadFromVatcode($vatcode);
        }
    }

    /**
     * Get the current fiscal year from the database
     *
     * @return int|null Fiscal year or null if not found
     */
    private static function getFiscalYear(){
        $query = db_select("SELECT kodenr FROM grupper WHERE art = 'RA' ORDER BY kodenr DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
        if (db_num_rows($query) > 0) {
            $r = db_fetch_array($query);
            return (int)$r['kodenr'];
        }
        return null;
    }

    /**
     * Load item details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id)
    {
        $regnaar = self::getFiscalYear();

        $qtxt = "SELECT * FROM grupper WHERE id = $id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->momskode = $r['kode'];
            $this->nr = $r['kodenr'];
            $this->fiscal_year = $r['fiscal_year'];

            $this->account = (int)$r['box1'];
            $this->sats = (float)$r['box2'];
            $this->modkonto = (int)$r['box3'];
            $this->map = $r['box4'];

            return true;
        }

        return false;
    }

    /**
     * Load item details from database by kodenr
     * 
     * @param int $kodenr
     * @return VatModel[] Array of VatModel objects
     */
    public static function loadFromVatcode($vatcode)
    {
        $regnaar = self::getFiscalYear();

        /* $momskode = $vatcode[0];
        $nr = substr($vatcode, 1);

        $qtxt = "SELECT * FROM grupper WHERE fiscal_year = $regnaar AND art IN ('SM','KM','EM','YM') AND kodenr = '$nr' AND kode='$momskode'"; */
        
        $qtxt = "SELECT id FROM grupper WHERE fiscal_year = $regnaar AND art IN ('SM','KM','EM','YM') AND kode = '$vatcode'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        // Check if query succeeded
        if ($q === false) {
            return [];
        }

        if (db_num_rows($q) > 0) {
            $items = [];
            while ($r = db_fetch_array($q)) {
                $items[] = new VatModel($r['id']);
            }
            return $items;
        }

        return [];
    }


    /**
     * Save/update the current item
     *
     * @return bool Success status
     */
    public function save()
    {
        $regnaar = self::getFiscalYear();

        if ($this->id) {
            // Update existing item
            $qtxt = "UPDATE grupper SET 
                beskrivelse = '$this->beskrivelse', 
                kodenr = '$this->nr', 
                kode = '$this->momskode', 
                box1 = '$this->account', 
                box2 = '$this->sats', 
                box3 = '$this->modkonto', 
                box4 = '$this->map'
            WHERE id = $this->id";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            return explode("\t", $q)[0] == "0";
        } else {
            // Insert new item
            $qtxt = "INSERT INTO grupper (
                art, 
                beskrivelse, 
                kodenr, 
                kode, 
                fiscal_year, 
                box1, 
                box2, 
                box3, 
                box4
            ) VALUES (
                '" . $this->momskode . "M', 
                '$this->beskrivelse', 
                '$this->nr', 
                '$this->momskode', 
                '$regnaar', 
                '$this->account', 
                '$this->sats', 
                '$this->modkonto', 
                '$this->map'
            )";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

            $query = db_select("SELECT id FROM grupper WHERE art = '" . $this->momskode . "M' AND fiscal_year = $regnaar AND kodenr = '$this->nr' AND kode = '$this->momskode' ORDER BY id DESC", __FILE__ . " linje " . __LINE__);

            if(db_num_rows($query) > 0) {
                // Get the last inserted ID
                $this->id = db_fetch_array($query)['id'];
            } else {
                // If insert failed, return false
                return false;
            }
            // If insert is successful, set the new ID
            return explode("\t", $q)[0] == "0";
        }
    }

    /**
     * Delete the current item
     * 
     * @return bool Success status
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        $qtxt = "DELETE FROM grupper WHERE id = $this->id";
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return explode("\t", $q)[0] == "0";
    }

    /**
     * Class method to get all VAT items
     *
     * @param string $orderBy Column to order by (default: kodenr)
     * @param string $orderDirection Sort direction (default: ASC)
     * @return VatModel[] Array of VAT objects
     */
    public static function getAllItems($orderBy = 'kodenr', $orderDirection = 'ASC')
    {
        $regnaar = self::getFiscalYear();

        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'kodenr', 'beskrivelse', 'fiscal_year'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'kodenr';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $qtxt = "SELECT id FROM grupper WHERE art IN ('SM','KM','EM','YM') AND fiscal_year = $regnaar ORDER BY $orderBy $orderDirection";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new VatModel($r['id']);
        }
        return $items;
    }

    /**
     * Class method to find VAT items by a specific field
     *
     * @param string $field Field to search
     * @param string $value Value to match
     * @return VatModel[] Array of matching VAT objects
     */
    public static function findBy($field, $value)
    {
        $regnaar = self::getFiscalYear();

        // Whitelist allowed search fields
        $allowedFields = ['id', 'kodenr', 'beskrivelse', 'fiscal_year', 'kode'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }

        $qtxt = "SELECT id FROM grupper WHERE art IN ('SM','KM','EM','YM') AND fiscal_year = $regnaar AND $field = '$value'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new VatModel($r['id']);
        }
        return $items;
    }

    /**
     * Method to convert object to array with English field names
     *
     * @return array Associative array of item properties
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'description' => $this->beskrivelse,
            'vatCode' => $this->momskode,
            'number' => $this->nr,
            'fiscalYear' => $this->fiscal_year,
            'account' => $this->account,
            'rate' => $this->sats,
            'contraAccount' => $this->modkonto,
            'mapping' => $this->map
        ];
    }

    // Getter methods
    public function getId()
    {
        return $this->id;
    }

    public function getBeskrivelse()
    {
        return $this->beskrivelse;
    }

    public function getMomskode()
    {
        return $this->momskode;
    }

    public function getNr()
    {
        return $this->nr;
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function getSats()
    {
        return $this->sats;
    }

    public function getModkonto()
    {
        return $this->modkonto;
    }

    public function getMap()
    {
        return $this->map;
    }

    // Additional English getters for consistency
    public function getDescription()
    {
        return $this->beskrivelse;
    }

    public function getVatCode()
    {
        return $this->momskode;
    }

    public function getNumber()
    {
        return $this->nr;
    }

    public function getRate()
    {
        return $this->sats;
    }

    public function getContraAccount()
    {
        return $this->modkonto;
    }

    public function getMapping()
    {
        return $this->map;
    }

    // Setter methods
    public function setBeskrivelse($beskrivelse)
    {
        $this->beskrivelse = $beskrivelse;
    }

    public function setMomskode($momskode)
    {
        $this->momskode = $momskode;
    }

    public function setNr($nr)
    {
        $this->nr = $nr;
    }

    public function setFiscalYear($fiscal_year)
    {
        $this->fiscal_year = $fiscal_year;
    }

    public function setAccount($account)
    {
        $this->account = $account;
    }

    public function setSats($sats)
    {
        $this->sats = $sats;
    }

    public function setModkonto($modkonto)
    {
        $this->modkonto = $modkonto;
    }

    public function setMap($map)
    {
        $this->map = $map;
    }

    // Additional English setters for consistency
    public function setDescription($description)
    {
        $this->beskrivelse = $description;
    }

    public function setVatCode($vatCode)
    {
        $this->momskode = $vatCode;
    }

    public function setNumber($number)
    {
        $this->nr = $number;
    }

    public function setRate($rate)
    {
        $this->sats = $rate;
    }

    public function setContraAccount($contraAccount)
    {
        $this->modkonto = $contraAccount;
    }

    public function setMapping($mapping)
    {
        $this->map = $mapping;
    }
}