<?php

include_once __DIR__."/VatModel.php";

class AccountModel {
    // Private properties matching database columns
    private $id;
    private $kontonr;
    private $beskrivelse;
    private $kontotype;
    private $moms;
    private $fra_kto;
    private $til_kto;
    private $lukket;
    private $primo;
    private $saldo;
    private $regnskabsaar;
    private $genvej;
    private $overfor_til;
    private $anvendelse;
    private $modkonto;
    private $valuta;
    private $valutakurs;
    private $map_to;

    /**
     * Constructor - can create an empty Account or load an existing one by ID
     *
     * @param int|null $id Optional ID to load existing account
     */
    public function __construct($id = null, $kontonr = null) {
        if ($id !== null) {
            $this->loadFromId($id);
        }
        if ($kontonr !== null) {
            $this->loadFromKontonr($kontonr);
        }
    }

    /**
     * Load account details from database by ID
     *
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id) {
        $qtxt = "SELECT * FROM kontoplan WHERE id = $id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($r = db_fetch_array($q)) {
            $this->id = $r['id'];
            $this->kontonr = $r['kontonr'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->kontotype = $r['kontotype'];
            $this->moms = !empty($r['moms']) ? new VatModel($id=NULL, $vatcode=$r['moms']) : NULL;
            $this->fra_kto = $r['fra_kto'];
            $this->til_kto = $r['til_kto'];
            $this->lukket = $r['lukket'];
            $this->primo = $r['primo'];
            $this->saldo = $r['saldo'];
            $this->regnskabsaar = $r['regnskabsaar'];
            $this->genvej = $r['genvej'];
            $this->overfor_til = $r['overfor_til'];
            $this->anvendelse = $r['anvendelse'];
            $this->modkonto = $r['modkonto'];
            $this->valuta = $r['valuta'];
            $this->valutakurs = $r['valutakurs'];
            $this->map_to = $r['map_to'];
            return true;
        }
        return false;
    }

    /**
     * Load account details from database by ID
     *
     * @param int $kontonr
     * @return bool Success status
     */
    private function loadFromKontonr($kontonr) {
        global $regnaar;

        $qtxt = "SELECT * FROM kontoplan WHERE kontonr = $kontonr AND regnskabsaar = $regnaar";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->kontonr = (int)$r['kontonr'];
            $this->beskrivelse = $r['beskrivelse'];
            $this->kontotype = $r['kontotype'];
            $this->moms = !empty($r['moms']) ? new VatModel($id=NULL, $vatcode=$r['moms']) : NULL;
            $this->fra_kto = (int)$r['fra_kto'];
            $this->til_kto = (int)$r['til_kto'];
            $this->lukket = $r['lukket'];
            $this->primo = (float)$r['primo'];
            $this->saldo = (float)$r['saldo'];
            $this->regnskabsaar = (int)$r['regnskabsaar'];
            $this->genvej = $r['genvej'];
            $this->overfor_til = $r['overfor_til'];
            $this->anvendelse = $r['anvendelse'];
            $this->modkonto = $r['modkonto'];
            $this->valuta = $r['valuta'];
            $this->valutakurs = $r['valutakurs'];
            $this->map_to = $r['map_to'];
            return true;
        }
        return false;
    }

    /**
     * Save/update the current account
     *
     * @return bool Success status
     */
    public function save() {
        if ($this->id) {
            // Update existing account
            $qtxt = "UPDATE kontoplan SET 
                kontonr = '$this->kontonr', 
                beskrivelse = '$this->beskrivelse', 
                kontotype = '$this->kontotype', 
                moms = '".($this->moms !== NULL ? $this->moms->getMomskode().$this->moms->getNr() : "")."',
                fra_kto = '$this->fra_kto', 
                til_kto = '$this->til_kto', 
                lukket = '$this->lukket', 
                primo = '$this->primo', 
                saldo = '$this->saldo', 
                regnskabsaar = '$this->regnskabsaar', 
                genvej = '$this->genvej', 
                overfor_til = '$this->overfor_til', 
                anvendelse = '$this->anvendelse', 
                modkonto = '$this->modkonto', 
                valuta = '$this->valuta', 
                valutakurs = '$this->valutakurs', 
                map_to = '$this->map_to' 
                WHERE id = $this->id";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            return explode("\t", $q)[0] == "0";
        } else {

            // get valutakurs from grupper where art = VK and box1 = $this->valuta
            if ($this->valuta) {
                $qtxt = "SELECT box2, kodenr FROM grupper WHERE art = 'VK' AND (UPPER(box1) = UPPER('$this->valuta'))";
                $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
                if (db_num_rows($q) > 0) {
                    $r = db_fetch_array($q);
                    // if valutakurs is not given by the user use the one from the database
                    if(!$this->valutakurs) {
                        $this->valutakurs = (float)$r['box2'];
                    }
                    $this->valuta = $r['kodenr']; // Set the currency code from the database
                } else {
                    if(!$this->valutakurs) {
                        $this->valutakurs = 100; // Default to 100 if no currency found
                    }
                    $this->valuta = 0; // Default to 0 if no currency found
                }
            }

            // Insert new account
            $qtxt = "INSERT INTO kontoplan (
                kontonr, beskrivelse, kontotype, moms, fra_kto, til_kto, 
                lukket, primo, saldo, regnskabsaar, genvej, overfor_til, 
                anvendelse, modkonto, valuta, valutakurs, map_to
            ) VALUES (
                '$this->kontonr', '$this->beskrivelse', '$this->kontotype', 
                '$this->moms', '$this->fra_kto', '$this->til_kto', 
                '$this->lukket', '$this->primo', '$this->saldo', 
                '$this->regnskabsaar', '$this->genvej', '$this->overfor_til', 
                '$this->anvendelse', '$this->modkonto', '$this->valuta', 
                '$this->valutakurs', '$this->map_to'
            )";
            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            
            $query = db_select("SELECT id FROM kontoplan WHERE 
                kontonr = '$this->kontonr' AND regnskabsaar = '$this->regnskabsaar'", 
                __FILE__ . " linje " . __LINE__);
            if(db_num_rows($query) > 0) {
                $r = db_fetch_array($query);
                $this->id = (int)$r['id'];
            }
            // If insert is successful, set the new ID
            return explode("\t", $q)[0] == "0";
        }
    }

    /**
     * Class method to get all accounts
     *
     * @param string $orderBy Column to order by (default: kontonr)
     * @param string $orderDirection Sort direction (default: ASC)
     * @return AccountModel[] Array of Account objects
     */
    public static function getAllItems($orderBy = 'kontonr', $orderDirection = 'ASC', $limit) {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['id', 'kontonr', 'beskrivelse', 'kontotype', 'regnskabsaar'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'kontonr';
        
        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';
        
        $qtxt = "SELECT id FROM kontoplan ORDER BY $orderBy $orderDirection LIMIT $limit";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new AccountModel($r['id']);
        }
        return $items;
    }

    /**
     * Class method to find accounts by a specific field
     *
     * @param string $field Field to search
     * @param string $value Value to match
     * @return AccountModel[] Array of matching Account objects
     */
    public static function findBy($field, $value) {
        // Whitelist allowed search fields
        $allowedFields = ['id', 'kontonr', 'beskrivelse', 'kontotype', 'regnskabsaar', 'system_account'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }
        
        $qtxt = "SELECT id FROM kontoplan WHERE $field = ?";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new AccountModel($r['id']);
        }
        return $items;
    }

    /**
     * Method to convert object to array
     *
     * @return array Associative array of account properties
     */
    public function toArray() {
        return array(
            'id' => $this->id,
            'kontonr' => $this->kontonr,
            'beskrivelse' => $this->beskrivelse,
            'kontotype' => $this->kontotype,
            'moms' => $this->moms,
            'fra_kto' => $this->fra_kto,
            'til_kto' => $this->til_kto,
            'lukket' => $this->lukket,
            'primo' => $this->primo,
            'saldo' => $this->saldo,
            'regnskabsaar' => $this->regnskabsaar,
            'genvej' => $this->genvej,
            'overfor_til' => $this->overfor_til,
            'anvendelse' => $this->anvendelse,
            'modkonto' => $this->modkonto,
            'valuta' => $this->valuta,
            'valutakurs' => $this->valutakurs,
            'map_to' => $this->map_to
        );
    }

    // Getter methods
    public function getId() { return $this->id; }
    public function getKontonr() { return $this->kontonr; }
    public function getBeskrivelse() { return $this->beskrivelse; }
    public function getKontotype() { return $this->kontotype; }
    public function getMoms() { return $this->moms; }
    public function getFraKto() { return $this->fra_kto; }
    public function getTilKto() { return $this->til_kto; }
    public function getLukket() { return $this->lukket; }
    public function getPrimo() { return $this->primo; }
    public function getSaldo() { return $this->saldo; }
    public function getRegnskabsaar() { return $this->regnskabsaar; }
    public function getGenvej() { return $this->genvej; }
    public function getOverforTil() { return $this->overfor_til; }
    public function getAnvendelse() { return $this->anvendelse; }
    public function getModkonto() { return $this->modkonto; }
    public function getValuta() { return $this->valuta; }
    public function getValutakurs() { return $this->valutakurs; }
    public function getMapTo() { return $this->map_to; }

    // Setter methods
    public function setKontonr($kontonr) { $this->kontonr = $kontonr; }
    public function setBeskrivelse($beskrivelse) { $this->beskrivelse = $beskrivelse; }
    public function setKontotype($kontotype) { $this->kontotype = $kontotype; }
    public function setMoms($moms) { $this->moms = $moms; }
    public function setFraKto($fra_kto) { $this->fra_kto = $fra_kto; }
    public function setTilKto($til_kto) { $this->til_kto = $til_kto; }
    public function setLukket($lukket) { $this->lukket = $lukket; }
    public function setPrimo($primo) { $this->primo = $primo; }
    public function setSaldo($saldo) { $this->saldo = $saldo; }
    public function setRegnskabsaar($regnskabsaar) { $this->regnskabsaar = $regnskabsaar; }
    public function setGenvej($genvej) { $this->genvej = $genvej; }
    public function setOverforTil($overfor_til) { $this->overfor_til = $overfor_til; }
    public function setAnvendelse($anvendelse) { $this->anvendelse = $anvendelse; }
    public function setModkonto($modkonto) { $this->modkonto = $modkonto; }
    public function setValuta($valuta) { $this->valuta = $valuta; }
    public function setValutakurs($valutakurs) { $this->valutakurs = $valutakurs; }
    public function setMapTo($map_to) { $this->map_to = $map_to; }
}