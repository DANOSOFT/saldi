<?php
include_once __DIR__ . "/../finans/AccountModel.php";

class VareGruppeModel
{
    // Properties to match database columns
    private $id;
    private $description;
    private $codeNo;
    private $fiscalYear;

    # Boolean options
    private $reversePayment;
    private $taxFree;
    private $inventory;
    private $batch;
    private $operation;

    # Konti
    private $buyAccount;
    private $sellAccount;
    private $buyEuAccount;
    private $sellEuAccount;
    private $buyOutsideEuAccount;
    private $sellOutsideEuAccount;

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
            $this->description = $r['beskrivelse'];
            $this->codeNo = (int)$r['kodenr'];
            $this->fiscalYear = (int)$r['fiscal_year'];

            $this->reversePayment = $r['box6'];
            $this->taxFree = $r['box7'];
            $this->inventory = $r['box8'];
            $this->batch = $r['box9'];
            $this->operation = $r['box10'];

            $this->buyAccount = $r['box3'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box3']) : NULL;
            $this->sellAccount = $r['box4'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box4']) : NULL;
            $this->buyEuAccount = $r['box11'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box11']) : NULL;
            $this->sellEuAccount = $r['box12'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box12']) : NULL;
            $this->buyOutsideEuAccount = $r['box2'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box2']) : NULL;
            $this->sellOutsideEuAccount = $r['box14'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box14']) : NULL;

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
            $this->description = $r['beskrivelse'];
            $this->codeNo = (int)$r['kodenr'];
            $this->fiscalYear = (int)$r['fiscal_year'];

            $this->reversePayment = $r['box6'];
            $this->taxFree = $r['box7'];
            $this->inventory = $r['box8'];
            $this->batch = $r['box9'];
            $this->operation = $r['box10'];

            $this->buyAccount = $r['box3'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box3']) : NULL;
            $this->sellAccount = $r['box4'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box4']) : NULL;
            $this->buyEuAccount = $r['box11'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box11']) : NULL;
            $this->sellEuAccount = $r['box12'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box12']) : NULL;
            $this->buyOutsideEuAccount = $r['box2'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box2']) : NULL;
            $this->sellOutsideEuAccount = $r['box14'] != "" ? new AccountModel($id = NULL, $kontonr = $r['box14']) : NULL;

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
            $buy_account_val = is_object($this->buyAccount) ? $this->buyAccount->getKontonr() : $this->buyAccount;
            $sell_account_val = is_object($this->sellAccount) ? $this->sellAccount->getKontonr() : $this->sellAccount;
            $buy_eu_account_val = is_object($this->buyEuAccount) ? $this->buyEuAccount->getKontonr() : $this->buyEuAccount;
            $sell_eu_account_val = is_object($this->sellEuAccount) ? $this->sellEuAccount->getKontonr() : $this->sellEuAccount;
            $buy_outside_eu_account_val = is_object($this->buyOutsideEuAccount) ? $this->buyOutsideEuAccount->getKontonr() : $this->buyOutsideEuAccount;
            $sell_outside_eu_account_val = is_object($this->sellOutsideEuAccount) ? $this->sellOutsideEuAccount->getKontonr() : $this->sellOutsideEuAccount;
            
            $qtxt = "UPDATE grupper SET 
                beskrivelse = '$this->description', 
                kodenr = '$this->codeNo', 
                fiscal_year = '$this->fiscalYear', 
                box6 = '$this->reversePayment', 
                box7 = '$this->taxFree', 
                box8 = '$this->inventory', 
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
            $buy_account_val = is_object($this->buyAccount) ? $this->buyAccount->getKontonr() : $this->buyAccount;
            $sell_account_val = is_object($this->sellAccount) ? $this->sellAccount->getKontonr() : $this->sellAccount;
            $buy_eu_account_val = is_object($this->buyEuAccount) ? $this->buyEuAccount->getKontonr() : $this->buyEuAccount;
            $sell_eu_account_val = is_object($this->sellEuAccount) ? $this->sellEuAccount->getKontonr() : $this->sellEuAccount;
            $buy_outside_eu_account_val = is_object($this->buyOutsideEuAccount) ? $this->buyOutsideEuAccount->getKontonr() : $this->buyOutsideEuAccount;
            $sell_outside_eu_account_val = is_object($this->sellOutsideEuAccount) ? $this->sellOutsideEuAccount->getKontonr() : $this->sellOutsideEuAccount;

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
                '$this->description', 
                '$this->codeNo', 
                '$regnaar', 
                '$this->reversePayment', 
                '$this->taxFree', 
                '$this->inventory', 
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
            'codeNo' => $this->codeNo,
            'description' => $this->description,
            'fiscalYear' => $this->fiscalYear,
            'reversePayment' => $this->reversePayment == "on",
            'taxFree' => $this->taxFree == "on",
            'inventory' => $this->inventory == "on",
            'batch' => $this->batch == "on",
            'operation' => $this->operation == "on",
            'accounts' => array(
                'buyAccount' => ($this->buyAccount !== NULL && is_object($this->buyAccount)) ? $this->buyAccount->toArray() : $this->buyAccount,
                'sellAccount' => ($this->sellAccount !== NULL && is_object($this->sellAccount)) ? $this->sellAccount->toArray() : $this->sellAccount,
                'buyEuAccount' => ($this->buyEuAccount !== NULL && is_object($this->buyEuAccount)) ? $this->buyEuAccount->toArray() : $this->buyEuAccount,
                'sellEuAccount' => ($this->sellEuAccount !== NULL && is_object($this->sellEuAccount)) ? $this->sellEuAccount->toArray() : $this->sellEuAccount,
                'buyOutsideEuAccount' => ($this->buyOutsideEuAccount !== NULL && is_object($this->buyOutsideEuAccount)) ? $this->buyOutsideEuAccount->toArray() : $this->buyOutsideEuAccount,
                'sellOutsideEuAccount' => ($this->sellOutsideEuAccount !== NULL && is_object($this->sellOutsideEuAccount)) ? $this->sellOutsideEuAccount->toArray() : $this->sellOutsideEuAccount
            )
        );
    }

    // Getter methods
    public function getId()
    {
        return $this->id;
    }
    public function getCodeNo()
    {
        return $this->codeNo;
    }
    public function getFiscalYear()
    {
        return $this->fiscalYear;
    }
    public function getDescription()
    {
        return $this->description;
    }
    public function getReversePayment()
    {
        return $this->reversePayment;
    }
    public function getTaxFree()
    {
        return $this->taxFree;
    }
    public function getInventory()
    {
        return $this->inventory;
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
        return $this->buyAccount;
    }
    public function getSellAccount()
    {
        return $this->sellAccount;
    }
    public function getBuyEuAccount()
    {
        return $this->buyEuAccount;
    }
    public function getSellEuAccount()
    {
        return $this->sellEuAccount;
    }
    public function getBuyOutsideEuAccount()
    {
        return $this->buyOutsideEuAccount;
    }
    public function getSellOutsideEuAccount()
    {
        return $this->sellOutsideEuAccount;
    }

    // Setter methods
    public function setCodeNo($codeNo)
    {
        $this->codeNo = $codeNo;
    }
    public function setFiscalYear($fiscalYear)
    {
        $this->fiscalYear = $fiscalYear;
    }
    public function setDescription($description)
    {
        $this->description = $description;
    }
    public function setReversePayment($reversePayment)
    {
        $this->reversePayment = $reversePayment;
    }
    public function setTaxFree($taxFree)
    {
        $this->taxFree = $taxFree;
    }
    public function setInventory($inventory)
    {
        $this->inventory = $inventory;
    }
    public function setBatch($batch)
    {
        $this->batch = $batch;
    }
    public function setOperation($operation)
    {
        $this->operation = $operation;
    }
    public function setBuyAccount($buyAccount)
    {
        if (is_numeric($buyAccount) && $buyAccount != "") {
            $this->buyAccount = new AccountModel($id = NULL, $kontonr = $buyAccount);
        } elseif (is_object($buyAccount)) {
            $this->buyAccount = $buyAccount;
        } else {
            $this->buyAccount = NULL;
        }
    }
    public function setSellAccount($sellAccount)
    {
        if (is_numeric($sellAccount) && $sellAccount != "") {
            $this->sellAccount = new AccountModel($id = NULL, $kontonr = $sellAccount);
        } elseif (is_object($sellAccount)) {
            $this->sellAccount = $sellAccount;
        } else {
            $this->sellAccount = NULL;
        }
    }
    public function setBuyEuAccount($buyEuAccount)
    {
        if (is_numeric($buyEuAccount) && $buyEuAccount != "") {
            $this->buyEuAccount = new AccountModel($id = NULL, $kontonr = $buyEuAccount);
        } elseif (is_object($buyEuAccount)) {
            $this->buyEuAccount = $buyEuAccount;
        } else {
            $this->buyEuAccount = NULL;
        }
    }
    public function setSellEuAccount($sellEuAccount)
    {
        if (is_numeric($sellEuAccount) && $sellEuAccount != "") {
            $this->sellEuAccount = new AccountModel($id = NULL, $kontonr = $sellEuAccount);
        } elseif (is_object($sellEuAccount)) {
            $this->sellEuAccount = $sellEuAccount;
        } else {
            $this->sellEuAccount = NULL;
        }
    }
    public function setBuyOutsideEuAccount($buyOutsideEuAccount)
    {
        if (is_numeric($buyOutsideEuAccount) && $buyOutsideEuAccount != "") {
            $this->buyOutsideEuAccount = new AccountModel($id = NULL, $kontonr = $buyOutsideEuAccount);
        } elseif (is_object($buyOutsideEuAccount)) {
            $this->buyOutsideEuAccount = $buyOutsideEuAccount;
        } else {
            $this->buyOutsideEuAccount = NULL;
        }
    }
    public function setSellOutsideEuAccount($sellOutsideEuAccount)
    {
        if (is_numeric($sellOutsideEuAccount) && $sellOutsideEuAccount != "") {
            $this->sellOutsideEuAccount = new AccountModel($id = NULL, $kontonr = $sellOutsideEuAccount);
        } elseif (is_object($sellOutsideEuAccount)) {
            $this->sellOutsideEuAccount = $sellOutsideEuAccount;
        } else {
            $this->sellOutsideEuAccount = NULL;
        }
    }
}