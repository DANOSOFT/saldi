<?php
include_once "LagerStatusModel.php";

class LagerModel
{
    // Properties to match database columns
    private $id;
    private $beskrivelse;
    private $nr;
    private $fiscal_year;
    private $lagerstatus;

    /**
     * Constructor - can create an empty Vare or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing item
     */
    public function __construct($id = null, $kodenr = null, $vare_id = null)
    {
        global $regnaar;

        if ($id !== null) {
            $this->loadFromId($id);
        } else if ($kodenr !== null) {
            $this->loadFromKodenr($kodenr);
        } else {
            $this->id = -1;
            $this->beskrivelse = "";
            $this->nr = 1;
            $this->fiscal_year = $regnaar;
        }

        if ($vare_id !== null) {
            $this->lagerstatus = new LagerStatusModel(null, $vare_id, $this->nr);
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
            $this->nr = (int)$r['kodenr'];
            $this->fiscal_year = (int)$r['fiscal_year'];

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
    private function loadFromKodenr($kodenr)
    {
        global $regnaar;

        $qtxt = "SELECT * FROM grupper WHERE fiscal_year = $regnaar AND art = 'LG' AND kodenr = '$kodenr'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->nr = (int)$r['kodenr'];
            $this->fiscal_year = (int)$r['fiscal_year'];

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
                fiscal_year = '$this->fiscal_year'
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
            ) VALUES (
                'LG'
                '$this->beskrivelse', 
                '$this->nr', 
                '$this->fiscal_year',
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
    public static function getAllItems($vare_id = null, $orderBy = 'kodenr', $orderDirection = 'ASC')
    {
        global $regnaar;

        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'kodenr', 'beskrivelse', 'fiscal_year'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'kodenr';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $qtxt = "SELECT id FROM grupper WHERE art = 'LG' AND fiscal_year = $regnaar ORDER BY $orderBy $orderDirection";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new LagerModel($r['id'], null, $vare_id);
        }

        # There is no lager setup
        if (count($items) == 0) {
            $items[] = new LagerModel(NULL, NULL, $vare_id);
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

        $qtxt = "SELECT id FROM grupper WHERE art = 'LG' AND fiscal_year = $regnaar AND $field = '$value'";
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
        $data = [
            'id' => $this->id,
            'beskrivelse' => $this->beskrivelse,
            'nr' => $this->nr,
            'fiscal_year' => $this->fiscal_year,
        ];

        if ($this->lagerstatus->getId() !== null) {
            $data["lagerstatus"] = $this->lagerstatus->toArray();
        } else {
            $data["lagerstatus"] = null;
        }

        return $data;
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

    public function getNr()
    {
        return $this->nr;
    }

    public function getFiscalYear()
    {
        return $this->fiscal_year;
    }

    // Setter methods
    public function setBeskrivelse($beskrivelse)
    {
        $this->beskrivelse = $beskrivelse;
    }

    public function setNr($nr)
    {
        $this->nr = $nr;
    }

    public function setFiscalYear($fiscal_year)
    {
        $this->fiscal_year = $fiscal_year;
    }
}