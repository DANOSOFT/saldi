<?php
include_once __DIR__ . "/../finans/AccountModel.php";

class VareGruppeModel
{
    // Properties to match database columns
    private $id;
    private $beskrivelse;
    private $kodenr;
    private $fiscal_year;

    # Boolean options
    private $omv_bet;
    private $moms_fri;
    private $lager;
    private $batch;
    private $operation;

    # Konti
    private $buy_account;
    private $sell_account;
    private $buy_eu_account;
    private $sell_eu_account;
    private $buy_outside_eu_account;
    private $sell_outside_eu_account;

    /**
     * Constructor - can create an empty Vare or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing item
     */
    public function __construct($id = null, $kodenr = null)
    {
        if ($id !== null) {
            $this->loadFromId($id);
        }
        if ($kodenr !== null) {
            $this->loadFromKodenr($kodenr);
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
            $this->kodenr = (int)$r['kodenr'];
            $this->fiscal_year = (int)$r['fiscal_year'];

            $this->omv_bet = $r['box6'];
            $this->moms_fri = $r['box7'];
            $this->lager = $r['box8'];
            $this->batch = $r['box9'];
            $this->operation = $r['box10'];

            $this->buy_account = $r['box3'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box3']) : NULL;
            $this->sell_account = $r['box4'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box4']) : NULL;
            $this->buy_eu_account = $r['box11'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box11']) : NULL;
            $this->sell_eu_account = $r['box12'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box12']) : NULL;
            $this->buy_outside_eu_account = $r['box2'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box2']) : NULL;
            $this->sell_outside_eu_account = $r['box14'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box14']) : NULL;

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

        $qtxt = "SELECT * FROM grupper WHERE fiscal_year = $regnaar AND art='VG' AND kodenr = '$kodenr'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->kodenr = (int)$r['kodenr'];
            $this->fiscal_year = (int)$r['fiscal_year'];

            $this->omv_bet = $r['box6'];
            $this->moms_fri = $r['box7'];
            $this->lager = $r['box8'];
            $this->batch = $r['box9'];
            $this->operation = $r['box10'];

            $this->buy_account = $r['box3'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box3']) : NULL;
            $this->sell_account = $r['box4'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box4']) : NULL;
            $this->buy_eu_account = $r['box11'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box11']) : NULL;
            $this->sell_eu_account = $r['box12'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box12']) : NULL;
            $this->buy_outside_eu_account = $r['box2'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box2']) : NULL;
            $this->sell_outside_eu_account = $r['box14'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box14']) : NULL;

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
        global $regnaar;        if ($this->id) {
            // Update existing item
            $buy_account_val = is_object($this->buy_account) ? $this->buy_account->getKontonr() : $this->buy_account;
            $sell_account_val = is_object($this->sell_account) ? $this->sell_account->getKontonr() : $this->sell_account;
            $buy_eu_account_val = is_object($this->buy_eu_account) ? $this->buy_eu_account->getKontonr() : $this->buy_eu_account;
            $sell_eu_account_val = is_object($this->sell_eu_account) ? $this->sell_eu_account->getKontonr() : $this->sell_eu_account;
            $buy_outside_eu_account_val = is_object($this->buy_outside_eu_account) ? $this->buy_outside_eu_account->getKontonr() : $this->buy_outside_eu_account;
            $sell_outside_eu_account_val = is_object($this->sell_outside_eu_account) ? $this->sell_outside_eu_account->getKontonr() : $this->sell_outside_eu_account;
            
            $qtxt = "UPDATE grupper SET 
                beskrivelse = '$this->beskrivelse', 
                kodenr = '$this->kodenr', 
                fiscal_year = '$this->fiscal_year', 
                box6 = '$this->omv_bet', 
                box7 = '$this->moms_fri', 
                box8 = '$this->lager', 
                box9 = '$this->batch', 
                box10 = '$this->operation', 
                box3 = '$buy_account_val', 
                box4 = '$sell_account_val', 
                box11 = '$buy_eu_account_val', 
                box12 = '$sell_eu_account_val', 
                box13 = '$buy_outside_eu_account_val', 
                box14 = '$sell_outside_eu_account_val' 
            WHERE id = $this->id";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            
            return explode("\t", $q)[0] == "0";
        } else {
            // Insert new item
            $buy_account_val = is_object($this->buy_account) ? $this->buy_account->getKontonr() : $this->buy_account;
            $sell_account_val = is_object($this->sell_account) ? $this->sell_account->getKontonr() : $this->sell_account;
            $buy_eu_account_val = is_object($this->buy_eu_account) ? $this->buy_eu_account->getKontonr() : $this->buy_eu_account;
            $sell_eu_account_val = is_object($this->sell_eu_account) ? $this->sell_eu_account->getKontonr() : $this->sell_eu_account;
            $buy_outside_eu_account_val = is_object($this->buy_outside_eu_account) ? $this->buy_outside_eu_account->getKontonr() : $this->buy_outside_eu_account;
            $sell_outside_eu_account_val = is_object($this->sell_outside_eu_account) ? $this->sell_outside_eu_account->getKontonr() : $this->sell_outside_eu_account;
            
            $qtxt = "INSERT INTO grupper (
                art, 
                beskrivelse, 
                kodenr, 
                fiscal_year, 
                box6, 
                box7, 
                box8, 
                box9, 
                box10, 
                box3, 
                box4, 
                box11, 
                box12, 
                box13, 
                box14
            ) VALUES (
                'VG',
                '$this->beskrivelse', 
                '$this->kodenr', 
                '$regnaar', 
                '$this->omv_bet', 
                '$this->moms_fri', 
                '$this->lager', 
                '$this->batch', 
                '$this->operation', 
                '$buy_account_val', 
                '$sell_account_val', 
                '$buy_eu_account_val', 
                '$sell_eu_account_val', 
                '$buy_outside_eu_account_val', 
                '$sell_outside_eu_account_val'
            )";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            // SELECT the id from grupper i just inserted row
            /* this dosent exists $this->id = db_insert_id(); the function db_insert_id() dose not exists */
            
            $idQuery = db_select("SELECT currval('grupper_id_seq') as id", __FILE__ . " linje " . __LINE__);
            if ($r = db_fetch_array($idQuery)) {
                $this->id = (int)$r['id'];
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
     * Class method to get all VareGruppe items
     *
     * @param string $orderBy Column to order by (default: kodenr)
     * @param string $orderDirection Sort direction (default: ASC)
     * @return VareGruppeModel[] Array of VareGruppe objects
     */
    public static function getAllItems($orderBy = 'kodenr', $orderDirection = 'ASC')
    {
        global $regnaar;

        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'kodenr', 'beskrivelse', 'fiscal_year'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'kodenr';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $qtxt = "SELECT id FROM grupper WHERE art = 'VG' AND fiscal_year = '$regnaar' ORDER BY $orderBy $orderDirection";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new VareGruppeModel($r['id']);
        }
        return $items;
    }

    /**
     * Class method to find VareGruppe items by a specific field
     *
     * @param string $field Field to search
     * @param string $value Value to match
     * @return VareGruppeModel[] Array of matching VareGruppe objects
     */
    public static function findBy($field, $value)
    {
        global $regnaar;

        // Whitelist allowed search fields
        $allowedFields = ['id', 'kodenr', 'beskrivelse', 'fiscal_year'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }

        $qtxt = "SELECT id FROM grupper WHERE art = 'VG' AND fiscal_year = '$regnaar' AND $field = '" . db_escape_string($value) . "'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new VareGruppeModel($r['id']);
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
        return array(
            'id' => $this->id,
            'kodenr' => $this->kodenr,
            'beskrivelse' => $this->beskrivelse,
            'fiscal_year' => $this->fiscal_year,
            'omv_bet' => $this->omv_bet == "on",
            'moms_fri' => $this->moms_fri == "on",
            'lager' => $this->lager == "on",
            'batch' => $this->batch == "on",
            'operation' => $this->operation == "on",
            'accounts' => array(
                'buy_account' => ($this->buy_account !== NULL && is_object($this->buy_account)) ? $this->buy_account->toArray() : $this->buy_account,
                'sell_account' => ($this->sell_account !== NULL && is_object($this->sell_account)) ? $this->sell_account->toArray() : $this->sell_account,
                'buy_eu_account' => ($this->buy_eu_account !== NULL && is_object($this->buy_eu_account)) ? $this->buy_eu_account->toArray() : $this->buy_eu_account,
                'sell_eu_account' => ($this->sell_eu_account !== NULL && is_object($this->sell_eu_account)) ? $this->sell_eu_account->toArray() : $this->sell_eu_account,
                'buy_outside_eu_account' => ($this->buy_outside_eu_account !== NULL && is_object($this->buy_outside_eu_account)) ? $this->buy_outside_eu_account->toArray() : $this->buy_outside_eu_account,
                'sell_outside_eu_account' => ($this->sell_outside_eu_account !== NULL && is_object($this->sell_outside_eu_account)) ? $this->sell_outside_eu_account->toArray() : $this->sell_outside_eu_account
            )
        );
    }

    // Getter methods
    public function getId()
    {
        return $this->id;
    }
    public function getKodenr()
    {
        return $this->kodenr;
    }
    public function getFiscalYear()
    {
        return $this->fiscal_year;
    }
    public function getBeskrivelse()
    {
        return $this->beskrivelse;
    }
    public function getOmvBet()
    {
        return $this->omv_bet;
    }
    public function getMomsFri()
    {
        return $this->moms_fri;
    }
    public function getLager()
    {
        return $this->lager;
    }
    public function getBatch()
    {
        return $this->batch;
    }
    public function getOperation()
    {
        return $this->operation;
    }
    public function getBuyAccount()
    {
        return $this->buy_account;
    }
    public function getSellAccount()
    {
        return $this->sell_account;
    }
    public function getBuyEuAccount()
    {
        return $this->buy_eu_account;
    }
    public function getSellEuAccount()
    {
        return $this->sell_eu_account;
    }
    public function getBuyOutsideEuAccount()
    {
        return $this->buy_outside_eu_account;
    }
    public function getSellOutsideEuAccount()
    {
        return $this->sell_outside_eu_account;
    }

    // Setter methods
    public function setKodenr($kodenr)
    {
        $this->kodenr = $kodenr;
    }
    public function setFiscalYear($fiscal_year)
    {
        $this->fiscal_year = $fiscal_year;
    }
    public function setBeskrivelse($beskrivelse)
    {
        $this->beskrivelse = $beskrivelse;
    }
    public function setOmvBet($omv_bet)
    {
        $this->omv_bet = $omv_bet;
    }
    public function setMomsFri($moms_fri)
    {
        $this->moms_fri = $moms_fri;
    }
    public function setLager($lager)
    {
        $this->lager = $lager;
    }
    public function setBatch($batch)
    {
        $this->batch = $batch;
    }
    public function setOperation($operation)
    {
        $this->operation = $operation;
    }
    public function setBuyAccount($buy_account)
    {
        if (is_numeric($buy_account) && $buy_account != "") {
            $this->buy_account = new AccountModel($id = NULL, $kontonr = $buy_account);
        } elseif (is_object($buy_account)) {
            $this->buy_account = $buy_account;
        } else {
            $this->buy_account = NULL;
        }
    }
    public function setSellAccount($sell_account)
    {
        if (is_numeric($sell_account) && $sell_account != "") {
            $this->sell_account = new AccountModel($id = NULL, $kontonr = $sell_account);
        } elseif (is_object($sell_account)) {
            $this->sell_account = $sell_account;
        } else {
            $this->sell_account = NULL;
        }
    }
    public function setBuyEuAccount($buy_eu_account)
    {
        if (is_numeric($buy_eu_account) && $buy_eu_account != "") {
            $this->buy_eu_account = new AccountModel($id = NULL, $kontonr = $buy_eu_account);
        } elseif (is_object($buy_eu_account)) {
            $this->buy_eu_account = $buy_eu_account;
        } else {
            $this->buy_eu_account = NULL;
        }
    }
    public function setSellEuAccount($sell_eu_account)
    {
        if (is_numeric($sell_eu_account) && $sell_eu_account != "") {
            $this->sell_eu_account = new AccountModel($id = NULL, $kontonr = $sell_eu_account);
        } elseif (is_object($sell_eu_account)) {
            $this->sell_eu_account = $sell_eu_account;
        } else {
            $this->sell_eu_account = NULL;
        }
    }
    public function setBuyOutsideEuAccount($buy_outside_eu_account)
    {
        if (is_numeric($buy_outside_eu_account) && $buy_outside_eu_account != "") {
            $this->buy_outside_eu_account = new AccountModel($id = NULL, $kontonr = $buy_outside_eu_account);
        } elseif (is_object($buy_outside_eu_account)) {
            $this->buy_outside_eu_account = $buy_outside_eu_account;
        } else {
            $this->buy_outside_eu_account = NULL;
        }
    }
    public function setSellOutsideEuAccount($sell_outside_eu_account)
    {
        if (is_numeric($sell_outside_eu_account) && $sell_outside_eu_account != "") {
            $this->sell_outside_eu_account = new AccountModel($id = NULL, $kontonr = $sell_outside_eu_account);
        } elseif (is_object($sell_outside_eu_account)) {
            $this->sell_outside_eu_account = $sell_outside_eu_account;
        } else {
            $this->sell_outside_eu_account = NULL;
        }
    }
}