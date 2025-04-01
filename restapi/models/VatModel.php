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
     * Load item details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id)
    {
        global $regnaar;

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
     * @return bool Success status
     */
    private function loadFromVatcode($vatcode)
    {
        global $regnaar;

        $momskode = $vatcode[0];
        $nr = substr($vatcode, 1);

        $qtxt = "SELECT * FROM grupper WHERE fiscal_year = $regnaar AND art IN ('SM','KM','EM','YM') AND kodenr = '$nr' AND kode='$momskode'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->momskode = $r['kode'];
            $this->nr = $r['kodenr'];
            $this->fiscal_year = $r['fiscal_year'];

            $this->account = $r['box1'];
            $this->sats = (float)$r['box2'];
            $this->modkonto = $r['box3'];
            $this->map = $r['box4'];

            return true;
        }

        return false;
    }


    /**
     * Save/update the current item
     *
     * @return bool Success status
     */
    public function save()
    {
        global $regnaar;

        if ($this->id) {
            // Update existing item
            $qtxt = "UPDATE grupper SET 
                beskrivelse = '$this->beskrivelse', 
                kodenr = '$this->nr', 
                kode = '$this->momskode', 
                fiscal_year = '$this->fiscal_year', 
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
                '$this->fiscal_year', 
                '$this->account', 
                '$this->sats', 
                '$this->modkonto', 
                '$this->map'
            )";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

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

        $qtxt = "DELETE FROM grupper WHERE id = ?";
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
        global $regnaar;

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
        global $regnaar;

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
     * Method to convert object to array
     *
     * @return array Associative array of item properties
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'beskrivelse' => $this->beskrivelse,
            'momskode' => $this->momskode,
            'nr' => $this->nr,
            'fiscal_year' => $this->fiscal_year,
            'account' => $this->account,
            'sats' => $this->sats,
            'modkonto' => $this->modkonto,
            'map' => $this->map
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

    public function getFiscalYear()
    {
        return $this->fiscal_year;
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
}