<?php

include_once __DIR__ . "/VareGruppeModel.php";
include_once __DIR__ . "/LagerModel.php";

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

    /**
     * Constructor - can create an empty Vare or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing item
     */
    public function __construct($id = null)
    {
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
        $qtxt = "SELECT * FROM varer WHERE id = $id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->varenr = $r['varenr'];
            $this->stregkode = $r['stregkode'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->enhed = $r['enhed'];
            $this->enhed2 = $r['enhed2'];

            $this->salgspris = (float)$r['salgspris'];
            $this->kostpris = (float)$r['kostpris'];

            $this->lager = LagerModel::getAllItems($vare_id = $this->id);
            $this->gruppe = new VareGruppeModel($id = NULL, $kodenr = $r['gruppe']);

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
        if ($this->id) {
            // Update existing item
            $qtxt = "UPDATE varer SET stregkode = '$this->stregkode', beskrivelse = '$this->beskrivelse' WHERE id = $this->id";

            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

            return explode("\t", $q)[0] == "0";
        } else {
            // Insert new item
            $qtxt = "INSERT INTO varer (varenr, stregkode, beskrivelse) VALUES ('$this->varenr', '$this->stregkode', '$this->beskrivelse')";

            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

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

        $qtxt = "DELETE FROM varer WHERE id = ?";
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return explode("\t", $q)[0] == "0";
    }

    /**
     * Class method to get all items
     * 
     * @param string $orderBy Column to order by (default: varenr)
     * @param string $orderDirection Sort direction (default: ASC)
     * @return VareModel[] Array of Vare objects
     */
    public static function getAllItems($orderBy = 'varenr', $orderDirection = 'ASC')
    {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'varenr', 'stregkode', 'beskrivelse'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'varenr';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $qtxt = "SELECT id FROM varer ORDER BY $orderBy $orderDirection";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new VareModel($r['id']);
        }

        return $items;
    }

    /**
     * Class method to find items by a specific field
     * 
     * @param string $field Field to search
     * @param string $value Value to match
     * @return VareModel[] Array of matching Vare objects
     */
    public static function findBy($field, $value)
    {
        // Whitelist allowed search fields
        $allowedFields = ['id', 'varenr', 'stregkode', 'beskrivelse'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }

        $qtxt = "SELECT id FROM varer WHERE $field = ?";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new VareModel($r['id']);
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
        $lagere = array();
        foreach ($this->lager as $key => $value) {
            $lagere[] = $value->toArray();
        }

        return array(
            'id' => $this->id,
            'varenr' => $this->varenr,
            'stregkode' => $this->stregkode,
            'beskrivelse' => $this->beskrivelse,
            'enhed' => array(
                'enhed' => $this->enhed,
                'enhed2' => $this->enhed2
            ),
            'priser' => array(
                'salgspris' => $this->salgspris,
                'momspris' => $this->getPrisInklMoms(),
                'moms' => $this->getPrisInklMoms() - $this->salgspris,
                'kostpris' => $this->kostpris,
            ),
            'lager' => $lagere,
            'gruppe' => $this->gruppe->toArray(),
        );
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
}