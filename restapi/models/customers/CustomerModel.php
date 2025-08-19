<?php

class CustomerModel
{
    // Properties to match database columns from adresser table
    private $id;
    private $firmanavn;
    private $tlf;
    private $email;
    private $addr1;
    private $addr2;
    private $postnr;
    private $bynavn;
    private $cvrnr;
    private $land;
    private $bank_navn;
    private $bank_reg;
    private $bank_konto;
    private $bank_fi;
    private $notes;
    private $betalingsbet;
    private $betalingsdage;
    private $ean;
    private $fornavn;
    private $efternavn;
    private $lev_firmanavn;
    private $lev_addr1;
    private $lev_addr2;
    private $lev_postnr;
    private $lev_bynavn;
    private $lev_tlf;
    private $lev_email;
    private $lev_land;
    private $kontakt;
    private $art;
    private $gruppe;
    private $kontonr;

    /**
     * Constructor - can create an empty customer or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing customer
     */
    public function __construct($id = null, $art = null)
    {
        if ($id !== null) {
            $this->loadFromId($id, $art);
        }
    }

    /**
     * Load customer details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id, $art)
    {
        
        $qtxt = "SELECT * FROM adresser WHERE id = $id AND art = '$art'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if(!$q) {
            return false; // Query failed
        }
        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->firmanavn = $r['firmanavn'];
            $this->tlf = $r['tlf'];
            $this->email = $r['email'];
            $this->addr1 = $r['addr1'];
            $this->addr2 = $r['addr2'];
            $this->postnr = $r['postnr'];
            $this->bynavn = $r['bynavn'];
            $this->cvrnr = $r['cvrnr'];
            $this->land = $r['land'];
            $this->bank_navn = $r['bank_navn'];
            $this->bank_reg = $r['bank_reg'];
            $this->bank_konto = $r['bank_konto'];
            $this->bank_fi = $r['bank_fi'];
            $this->notes = $r['notes'];
            $this->betalingsbet = $r['betalingsbet'];
            $this->betalingsdage = (int)$r['betalingsdage'];
            $this->ean = $r['ean'];
            $this->fornavn = $r['fornavn'];
            $this->efternavn = $r['efternavn'];
            $this->lev_firmanavn = $r['lev_firmanavn'];
            $this->lev_addr1 = $r['lev_addr1'];
            $this->lev_addr2 = $r['lev_addr2'];
            $this->lev_postnr = $r['lev_postnr'];
            $this->lev_bynavn = $r['lev_bynavn'];
            $this->lev_tlf = $r['lev_tlf'];
            $this->lev_email = $r['lev_email'];
            $this->lev_land = $r['lev_land'];
            $this->kontakt = $r['kontakt'];

            return true;
        }

        return false;
    }

    /**
     * Save/insert the current customer
     * 
     * @param int|null $id Optional ID to set before saving (for updates)
     * @return bool Success status
     */
    public function save($id = null)
    {
        // If an ID is passed, set it for update operations
        if ($id !== null) {
            $this->id = $id;
        }

        // Set default values
        $this->gruppe = $this->gruppe ?: 1;
        
        // Handle integer fields - set to NULL if empty/null, otherwise ensure they're integers
        $betalingsdage = ($this->betalingsdage === null || $this->betalingsdage === '') ? 'NULL' : (int)$this->betalingsdage;
        $gruppe = ($this->gruppe === null || $this->gruppe === '') ? 1 : (int)$this->gruppe;

        // Escape strings for SQL
        $firmanavn = db_escape_string($this->firmanavn ?: '');
        $tlf = db_escape_string($this->tlf ?: '');
        $email = db_escape_string($this->email ?: '');
        $addr1 = db_escape_string($this->addr1 ?: '');
        $addr2 = db_escape_string($this->addr2 ?: '');
        $postnr = db_escape_string($this->postnr ?: '');
        $bynavn = db_escape_string($this->bynavn ?: '');
        $cvrnr = db_escape_string($this->cvrnr ?: '');
        $land = db_escape_string($this->land ?: '');
        $bank_navn = db_escape_string($this->bank_navn ?: '');
        $bank_reg = db_escape_string($this->bank_reg ?: '');
        $bank_konto = db_escape_string($this->bank_konto ?: '');
        $bank_fi = db_escape_string($this->bank_fi ?: '');
        $notes = db_escape_string($this->notes ?: '');
        $betalingsbet = db_escape_string($this->betalingsbet ?: '');
        $ean = db_escape_string($this->ean ?: '');
        $fornavn = db_escape_string($this->fornavn ?: '');
        $efternavn = db_escape_string($this->efternavn ?: '');
        $lev_firmanavn = db_escape_string($this->lev_firmanavn ?: '');
        $lev_addr1 = db_escape_string($this->lev_addr1 ?: '');
        $lev_addr2 = db_escape_string($this->lev_addr2 ?: '');
        $lev_postnr = db_escape_string($this->lev_postnr ?: '');
        $lev_bynavn = db_escape_string($this->lev_bynavn ?: '');
        $lev_tlf = db_escape_string($this->lev_tlf ?: '');
        $lev_email = db_escape_string($this->lev_email ?: '');
        $lev_land = db_escape_string($this->lev_land ?: '');
        $kontakt = db_escape_string($this->kontakt ?: '');
        $art = db_escape_string($this->art ?: '');
        $kontonr = db_escape_string($this->kontonr ?: $tlf); // Default to phone number if not set

        // If ID is set, we are updating an existing customer
        if ($this->id) {
            // Update existing customer
            // Build dynamic update query to only update fields that are set
            $updateFields = [];
            if ($this->firmanavn !== null) $updateFields[] = "firmanavn = '$firmanavn'";
            if ($this->tlf !== null) $updateFields[] = "tlf = '$tlf'";
            if ($this->email !== null) $updateFields[] = "email = '$email'";
            if ($this->addr1 !== null) $updateFields[] = "addr1 = '$addr1'";
            if ($this->addr2 !== null) $updateFields[] = "addr2 = '$addr2'";
            if ($this->postnr !== null) $updateFields[] = "postnr = '$postnr'";
            if ($this->bynavn !== null) $updateFields[] = "bynavn = '$bynavn'";
            if ($this->cvrnr !== null) $updateFields[] = "cvrnr = '$cvrnr'";
            if ($this->land !== null) $updateFields[] = "land = '$land'";
            if ($this->bank_navn !== null) $updateFields[] = "bank_navn = '$bank_navn'";
            if ($this->bank_reg !== null) $updateFields[] = "bank_reg = '$bank_reg'";
            if ($this->bank_konto !== null) $updateFields[] = "bank_konto = '$bank_konto'";
            if ($this->bank_fi !== null) $updateFields[] = "bank_fi = '$bank_fi'";
            if ($this->notes !== null) $updateFields[] = "notes = '$notes'";
            if ($this->betalingsbet !== null) $updateFields[] = "betalingsbet = '$betalingsbet'";
            if ($this->betalingsdage !== null) $updateFields[] = "betalingsdage = $betalingsdage";
            if ($this->ean !== null) $updateFields[] = "ean = '$ean'";
            if ($this->fornavn !== null) $updateFields[] = "fornavn = '$fornavn'";
            if ($this->efternavn !== null) $updateFields[] = "efternavn = '$efternavn'";
            if ($this->lev_firmanavn !== null) $updateFields[] = "lev_firmanavn = '$lev_firmanavn'";
            if ($this->lev_addr1 !== null) $updateFields[] = "lev_addr1 = '$lev_addr1'";
            if ($this->lev_addr2 !== null) $updateFields[] = "lev_addr2 = '$lev_addr2'";
            if ($this->lev_postnr !== null) $updateFields[] = "lev_postnr = '$lev_postnr'";
            if ($this->lev_bynavn !== null) $updateFields[] = "lev_bynavn = '$lev_bynavn'";
            if ($this->lev_tlf !== null) $updateFields[] = "lev_tlf = '$lev_tlf'";
            if ($this->lev_email !== null) $updateFields[] = "lev_email = '$lev_email'";
            if ($this->lev_land !== null) $updateFields[] = "lev_land = '$lev_land'";
            if ($this->kontakt !== null) $updateFields[] = "kontakt = '$kontakt'";
            if ($this->gruppe !== null) $updateFields[] = "gruppe = $gruppe";
            
            // Only proceed if there are fields to update
            if (!empty($updateFields)) {
                $qtxt = "UPDATE adresser SET " . implode(", ", $updateFields) . " WHERE id = $this->id AND art = '$art'";
                $result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
                $resultArray = explode("\t", $result);
                // Check if update was successful
                if ($resultArray[0] == "0") {
                    return true; // Update successful
                }
            }
            
            return false; // Update failed or no fields to update
        }

        // Insert new customer
        $qtxt = "INSERT INTO adresser (
            firmanavn, tlf, email, addr1, addr2, postnr, bynavn, cvrnr, land,
            bank_navn, bank_reg, bank_konto, bank_fi, notes, betalingsbet, betalingsdage,
            ean, fornavn, efternavn, lev_firmanavn, lev_addr1, lev_addr2, lev_postnr,
            lev_bynavn, lev_tlf, lev_email, lev_land, kontakt, art, gruppe, kontonr
        ) VALUES (
            '$firmanavn', '$tlf', '$email', '$addr1', '$addr2', '$postnr', '$bynavn',
            '$cvrnr', '$land', '$bank_navn', '$bank_reg', '$bank_konto', '$bank_fi',
            '$notes', '$betalingsbet', $betalingsdage, '$ean', '$fornavn',
            '$efternavn', '$lev_firmanavn', '$lev_addr1', '$lev_addr2', '$lev_postnr',
            '$lev_bynavn', '$lev_tlf', '$lev_email', '$lev_land', '$kontakt', '$art', $gruppe, '$kontonr'
        )";

        $result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        $resultArray = explode("\t", $result);
        
        // Check if insert was successful
        if ($resultArray[0] == "0") {
            // Get the inserted ID
            $qtxt = "SELECT CURRVAL(pg_get_serial_sequence('adresser', 'id')) AS id";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            
            if ($q && ($r = db_fetch_array($q))) {
                $this->id = (int)$r['id'];
                return true;
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Delete the current customer
     * 
     * @return bool Success status
     */
    public function delete($art)
    {
        if (!$this->id) {
            return false;
        }

        $qtxt = "DELETE FROM adresser WHERE id = $this->id AND art = '$art'";
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return explode("\t", $q)[0] == "0";
    }

    /**
     * Class method to get all customers
     * 
     * @param string $orderBy Column to order by (default: firmanavn)
     * @param string $orderDirection Sort direction (default: ASC)
     * @return CustomerModel[] Array of CustomerModel objects
     */
    public static function getAllItems($art, $orderBy = 'firmanavn', $orderDirection = 'ASC', $limit = 20)
    {
        $allowedOrderBy = ['id', 'firmanavn', 'tlf', 'email'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'firmanavn';
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $qtxt = "SELECT id FROM adresser WHERE art = '$art' ORDER BY $orderBy $orderDirection LIMIT $limit";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new CustomerModel($r['id'], $art);
        }

        return $items;
    }

    /**
     * Class method to find customers by a specific field
     * 
     * @param string $field Field to search
     * @param string $value Value to match
     * @return CustomerModel[] Array of matching Customer objects
     */
    public static function findBy($field, $value)
    {
        $allowedFields = ['id', 'firmanavn', 'email', 'tlf', 'cvrnr'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }

        $value = db_escape_string($value);
        $qtxt = "SELECT id FROM adresser WHERE art = 'D' AND $field = '$value'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new CustomerModel($r['id']);
        }

        return $items;
    }

    /**
     * Method to convert object to array
     * 
     * @return array Associative array of customer properties
     */
    public function toArray()
    {
        return array(
            'id' => $this->id,
            'firmanavn' => $this->firmanavn,
            'tlf' => $this->tlf,
            'email' => $this->email,
            'adresse' => array(
                'addr1' => $this->addr1,
                'addr2' => $this->addr2,
                'postnr' => $this->postnr,
                'bynavn' => $this->bynavn,
                'land' => $this->land
            ),
            'cvrnr' => $this->cvrnr,
            'bank' => array(
                'bank_navn' => $this->bank_navn,
                'bank_reg' => $this->bank_reg,
                'bank_konto' => $this->bank_konto,
                'bank_fi' => $this->bank_fi
            ),
            'betaling' => array(
                'betalingsbet' => $this->betalingsbet,
                'betalingsdage' => $this->betalingsdage
            ),
            'leveringsadresse' => array(
                'lev_firmanavn' => $this->lev_firmanavn,
                'lev_addr1' => $this->lev_addr1,
                'lev_addr2' => $this->lev_addr2,
                'lev_postnr' => $this->lev_postnr,
                'lev_bynavn' => $this->lev_bynavn,
                'lev_tlf' => $this->lev_tlf,
                'lev_email' => $this->lev_email,
                'lev_land' => $this->lev_land
            ),
            'ean' => $this->ean,
            'fornavn' => $this->fornavn,
            'efternavn' => $this->efternavn,
            'kontakt' => $this->kontakt,
            'notes' => $this->notes,
            'gruppe' => $this->gruppe
        );
    }

    /**
     * Check if email already exists for another customer
     * 
     * @param string $email Email to check
     * @param int|null $excludeId ID to exclude from check (for updates)
     * @return bool True if email exists
     */
    public static function emailExists($email, $excludeId = null)
    {
        $email = db_escape_string($email);
        $qtxt = "SELECT id FROM adresser WHERE art = 'D' AND email = '$email'";
        
        if ($excludeId) {
            $qtxt .= " AND id NOT IN ($excludeId)";
        }
        
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        return db_fetch_array($q) !== false;
    }

    /**
     * Check if phone number already exists for another customer
     * 
     * @param string $tlf Phone number to check
     * @param int|null $excludeId ID to exclude from check (for updates)
     * @return bool True if phone exists
     */
    public static function phoneExists($tlf, $excludeId = null)
    {
        $tlf = db_escape_string($tlf);
        $qtxt = "SELECT id FROM adresser WHERE art = 'D' AND tlf = '$tlf'";
        
        if ($excludeId) {
            $qtxt .= " AND id != $excludeId";
        }
        
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        return db_fetch_array($q) !== false;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getFirmanavn() { return $this->firmanavn; }
    public function getTlf() { return $this->tlf; }
    public function getEmail() { return $this->email; }
    public function getAddr1() { return $this->addr1; }
    public function getAddr2() { return $this->addr2; }
    public function getPostnr() { return $this->postnr; }
    public function getBynavn() { return $this->bynavn; }
    public function getCvrnr() { return $this->cvrnr; }
    public function getLand() { return $this->land; }
    public function getBankNavn() { return $this->bank_navn; }
    public function getBankReg() { return $this->bank_reg; }
    public function getBankKonto() { return $this->bank_konto; }
    public function getBankFi() { return $this->bank_fi; }
    public function getNotes() { return $this->notes; }
    public function getBetalingsbet() { return $this->betalingsbet; }
    public function getBetalingsdage() { return $this->betalingsdage; }
    public function getEan() { return $this->ean; }
    public function getFornavn() { return $this->fornavn; }
    public function getEfternavn() { return $this->efternavn; }
    public function getKontakt() { return $this->kontakt; }
    public function getGruppe() { return $this->gruppe; }
    public function getArt() { return $this->art; }

    // Setters for required fields
    public function setFirmanavn($firmanavn) { $this->firmanavn = $firmanavn; }
    public function setTlf($tlf) { $this->tlf = $tlf; }
    public function setEmail($email) { $this->email = $email; }
    public function setArt($art) { $this->art = $art; }

    // Setters for optional fields
    public function setAddr1($addr1) { $this->addr1 = $addr1; }
    public function setAddr2($addr2) { $this->addr2 = $addr2; }
    public function setPostnr($postnr) { $this->postnr = $postnr; }
    public function setBynavn($bynavn) { $this->bynavn = $bynavn; }
    public function setCvrnr($cvrnr) { $this->cvrnr = $cvrnr; }
    public function setLand($land) { $this->land = $land; }
    public function setBankNavn($bank_navn) { $this->bank_navn = $bank_navn; }
    public function setBankReg($bank_reg) { $this->bank_reg = $bank_reg; }
    public function setBankKonto($bank_konto) { $this->bank_konto = $bank_konto; }
    public function setBankFi($bank_fi) { $this->bank_fi = $bank_fi; }
    public function setNotes($notes) { $this->notes = $notes; }
    public function setBetalingsbet($betalingsbet) { $this->betalingsbet = $betalingsbet; }
    public function setBetalingsdage($betalingsdage) { $this->betalingsdage = $betalingsdage; }
    public function setEan($ean) { $this->ean = $ean; }
    public function setFornavn($fornavn) { $this->fornavn = $fornavn; }
    public function setEfternavn($efternavn) { $this->efternavn = $efternavn; }
    public function setKontakt($kontakt) { $this->kontakt = $kontakt; }
    public function setGruppe($gruppe) { $this->gruppe = $gruppe; }
    public function setKontonr($kontonr) { $this->kontonr = $kontonr; }

    // Delivery address setters
    public function setLevFirmanavn($lev_firmanavn) { $this->lev_firmanavn = $lev_firmanavn; }
    public function setLevAddr1($lev_addr1) { $this->lev_addr1 = $lev_addr1; }
    public function setLevAddr2($lev_addr2) { $this->lev_addr2 = $lev_addr2; }
    public function setLevPostnr($lev_postnr) { $this->lev_postnr = $lev_postnr; }
    public function setLevBynavn($lev_bynavn) { $this->lev_bynavn = $lev_bynavn; }
    public function setLevTlf($lev_tlf) { $this->lev_tlf = $lev_tlf; }
    public function setLevEmail($lev_email) { $this->lev_email = $lev_email; }
    public function setLevLand($lev_land) { $this->lev_land = $lev_land; }
}