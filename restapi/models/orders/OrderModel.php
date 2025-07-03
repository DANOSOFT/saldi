<?php

class OrderModel
{
    // Properties to match database columns
    private $id;
    private $konto_id;
    private $firmanavn;
    private $telefon;
    private $email;
    private $momssats;
    private $addr1;
    private $addr2;
    private $postnr;
    private $bynavn;
    private $land;
    private $lev_navn;
    private $lev_addr1;
    private $lev_addr2;
    private $lev_postnr;
    private $lev_bynavn;
    private $lev_land;
    private $ean;
    private $cvrnr;
    private $ordredate;
    private $notes;
    private $betalt;
    private $sum;
    private $kostpris;
    private $moms;
    private $valuta;
    private $betalingsbet;
    private $betalingsdage;
    private $kontonr;
    private $ref;
    private $status;
    private $ordrenr;
    private $valutakurs;
    private $art;
    /**
     * Constructor - can create an empty Order or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing order
     */
    public function __construct($id = null, $art = null)
    {
        if ($id !== null) {
            $this->loadFromId($id, $art);
        }
    }

    /**
     * Load order details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id, $art)
    {
        $qtxt = "SELECT * FROM ordrer WHERE id = $id AND art = '$art'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if (db_num_rows($q) > 0) {
            $r = db_fetch_array($q);
            $this->id = (int)$r['id'];
            $this->konto_id = (int)$r['konto_id'];
            $this->firmanavn = $r['firmanavn'];
            $this->telefon = $r['phone'];
            $this->email = $r['email'];
            $this->momssats = (float)$r['momssats'];
            $this->addr1 = $r['addr1'];
            $this->addr2 = $r['addr2'];
            $this->postnr = $r['postnr'];
            $this->bynavn = $r['bynavn'];
            $this->land = $r['land'];
            $this->lev_navn = $r['lev_navn'];
            $this->lev_addr1 = $r['lev_addr1'];
            $this->lev_addr2 = $r['lev_addr2'];
            $this->lev_postnr = $r['lev_postnr'];
            $this->lev_bynavn = $r['lev_bynavn'];
            $this->lev_land = $r['lev_land'];
            $this->ean = $r['ean'];
            $this->cvrnr = $r['cvrnr'];
            $this->ordredate = $r['ordredate'];
            $this->notes = $r['notes'];
            $this->betalt = $r['betalt'];
            $this->sum = (float)$r['sum'];
            $this->kostpris = (float)$r['kostpris'];
            $this->moms = (float)$r['moms'];
            $this->valuta = $r['valuta'];
            $this->betalingsbet = $r['betalingsbet'];
            $this->betalingsdage = (int)$r['betalingsdage'];
            $this->kontonr = $r['kontonr'];
            $this->ref = $r['ref'];
            $this->status = (int)$r['status'];
            $this->ordrenr = (int)$r['ordrenr'];
            $this->valutakurs = (float)$r['valutakurs'];

            return true;
        }

        return false;
    }

    /**
     * Save/update the current order
     * 
     * @return bool Success status
     */
    public function save()
    {
        // Insert new order
        $qtxt = "INSERT INTO ordrer (
            konto_id, firmanavn, phone, email, momssats, addr1, addr2, postnr, bynavn, land,
            lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_land, ean, cvrnr,
            ordredate, notes, betalt, sum, kostpris, moms, valuta, betalingsbet, betalingsdage,
            kontonr, ref, status, ordrenr, valutakurs, art
        ) VALUES (
            '$this->konto_id', '$this->firmanavn', '$this->telefon', '$this->email', '$this->momssats',
            '$this->addr1', '$this->addr2', '$this->postnr', '$this->bynavn', '$this->land',
            '$this->lev_navn', '$this->lev_addr1', '$this->lev_addr2', '$this->lev_postnr', '$this->lev_bynavn',
            '$this->lev_land', '$this->ean', '$this->cvrnr', '$this->ordredate', '$this->notes',
            '$this->betalt', '$this->sum', '$this->kostpris', '$this->moms', '$this->valuta',
            '$this->betalingsbet', '$this->betalingsdage', '$this->kontonr', '$this->ref',
            '$this->status', '$this->ordrenr', '$this->valutakurs', '$this->art'
        )";

        $result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        $resultArray = explode("\t", $result);
        
        // Check if insert was successful
        if ($resultArray[0] == "0") {
            // Insert was successful, now get the inserted ID
            // Use PostgreSQL's CURRVAL() to get the last value from the sequence
            $qtxt = "SELECT CURRVAL(pg_get_serial_sequence('ordrer', 'id')) AS id";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            
            if ($q && ($r = db_fetch_array($q))) {
                $this->id = (int)$r['id'];
                return true;
            }
            
            // Fallback: try to find the order by unique fields
            $qtxt = "SELECT id FROM ordrer WHERE ordrenr = '$this->ordrenr' AND kontonr = '$this->kontonr' ORDER BY id DESC LIMIT 1";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            
            if ($q && ($r = db_fetch_array($q))) {
                $this->id = (int)$r['id'];
                return true;
            }
            
            // If we can't get the ID but insert was successful, still return true
            return true;
        }
        
        // Insert failed
        return false;
    }

    /**
     * Delete the current order
     * 
     * @return bool Success status
     */
/*     public function delete()
    {
        if (!$this->id) {
            return false;
        }

        $qtxt = "DELETE FROM ordrer WHERE id = $this->id";
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return explode("\t", $q)[0] == "0";
    } */

    /**
     * Class method to get all orders
     * 
     * @param string $art Order type (e.g., 'KO' for kreditor)
     * @param int $limit Number of records to return
     * @param string $orderBy Column to order by (default: ordrenr)
     * @param string $orderDirection Sort direction (default: DESC)
     * @param string|null $fromDate Start date filter (YYYY-MM-DD)
     * @param string|null $toDate End date filter (YYYY-MM-DD)
     * @return OrderModel[] Array of OrderModel objects
     */
    public static function getAllItems($art, $limit = 20, $orderBy = 'ordrenr', $orderDirection = 'DESC', $fromDate = null, $toDate = null)
    {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = ['ordrenr', 'id', 'postnr', 'firmanavn', 'ordredate'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'ordrenr';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';
        
        // Validate and sanitize limit
        $limit = (int)$limit;
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }
        
        // Escape the art parameter
        $art = pg_escape_string($art);
        
        // Build the base query
        $whereClause = "WHERE art = '$art'";
        
        // Add date filtering if provided - handle DATE type properly
        if ($fromDate || $toDate) {
            $formattedFromDate = self::formatDate($fromDate);
            $formattedToDate = self::formatDate($toDate);
            
            if ($formattedFromDate && $formattedToDate) {
                $whereClause .= " AND ordredate >= DATE('$formattedFromDate') AND ordredate <= DATE('$formattedToDate')";
            } elseif ($formattedFromDate) {
                $whereClause .= " AND ordredate >= DATE('$formattedFromDate')";
            } elseif ($formattedToDate) {
                $whereClause .= " AND ordredate <= DATE('$formattedToDate')";
            }
        }
        
        // Build the complete query
        $qtxt = "SELECT id FROM ordrer $whereClause ORDER BY $orderBy $orderDirection LIMIT $limit";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        if ($q && db_num_rows($q) > 0) {
            while ($r = db_fetch_array($q)) {
                // Pass the art parameter to the constructor
                $items[] = new OrderModel($r['id'], $art);
            }
        }

        return $items;
    }

    /**
     * Helper method to validate and format date
     * 
     * @param string $date Input date
     * @return string|null Formatted date or null if invalid
     */
    private static function formatDate($date)
    {
        if (!$date) {
            return null;
        }
        
        // Try to parse various date formats
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'];
        
        foreach ($formats as $format) {
            $dateObj = DateTime::createFromFormat($format, $date);
            if ($dateObj && $dateObj->format($format) === $date) {
                return $dateObj->format('Y-m-d'); // Return in database format
            }
        }
        
        return null;
    }

    /**
     * Class method to find orders by a specific field
     * 
     * @param string $field Field to search
     * @param string $value Value to match
     * @return OrderModel[] Array of matching Order objects
     */
    public static function findBy($field, $value, $art)
    {
        // Whitelist allowed search fields
        $allowedFields = ['id', 'ordrenr', 'firmanavn', 'email', 'phone', 'status'];
        if (!in_array($field, $allowedFields)) {
            return [];
        }

        $qtxt = "SELECT id FROM ordrer WHERE $field = '$value'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $items = [];
        while ($r = db_fetch_array($q)) {
            $items[] = new OrderModel($r['id']);
        }

        return $items;
    }

    /**
     * Method to convert object to array
     * 
     * @return array Associative array of order properties
     */
    public function toArray()
    {
        return array(
            'id' => $this->id,
            'konto_id' => $this->konto_id,
            'firmanavn' => $this->firmanavn,
            'telefon' => $this->telefon,
            'email' => $this->email,
            'momssats' => $this->momssats,
            'adresse' => array(
                'addr1' => $this->addr1,
                'addr2' => $this->addr2,
                'postnr' => $this->postnr,
                'bynavn' => $this->bynavn,
                'land' => $this->land
            ),
            'levering' => array(
                'lev_navn' => $this->lev_navn,
                'lev_addr1' => $this->lev_addr1,
                'lev_addr2' => $this->lev_addr2,
                'lev_postnr' => $this->lev_postnr,
                'lev_bynavn' => $this->lev_bynavn,
                'lev_land' => $this->lev_land
            ),
            'ean' => $this->ean,
            'cvrnr' => $this->cvrnr,
            'ordredate' => $this->ordredate,
            'notes' => $this->notes,
            'betalt' => $this->betalt,
            'betalingsinfo' => array(
                'betalingsbet' => $this->betalingsbet,
                'betalingsdage' => $this->betalingsdage
            ),
            'okonomi' => array(
                'sum' => $this->sum,
                'kostpris' => $this->kostpris,
                'moms' => $this->moms,
                'valuta' => $this->valuta,
                'valutakurs' => $this->valutakurs
            ),
            'kontonr' => $this->kontonr,
            'ref' => $this->ref,
            'status' => $this->status,
            'ordrenr' => $this->ordrenr
        );
    }

    // Getter methods
    public function getId() { return $this->id; }
    public function getKontoId() { return $this->konto_id; }
    public function getFirmanavn() { return $this->firmanavn; }
    public function getTelefon() { return $this->telefon; }
    public function getEmail() { return $this->email; }
    public function getMomssats() { return $this->momssats; }
    public function getOrdrenr() { return $this->ordrenr; }
    public function getStatus() { return $this->status; }
    public function getRef() { return $this->ref; }
    public function getValuta() { return $this->valuta; }
    public function getValutakurs() { return $this->valutakurs; }
    public function getBetalingsbet() { return $this->betalingsbet; }
    public function getBetalingsdage() { return $this->betalingsdage; }
    public function getKontonr() { return $this->kontonr; }
    public function getSum() { return $this->sum; }
    public function getKostpris() { return $this->kostpris; }
    public function getMoms() { return $this->moms; }
    public function getBetalt() { return $this->betalt; }
    public function getOrdredate() { return $this->ordredate; }
    public function getNotes() { return $this->notes; }
    public function getArt() { return $this->art; }

    // Setter methods - ALL REQUIRED SETTERS
    public function setKontoId($konto_id) { $this->konto_id = $konto_id; }
    public function setFirmanavn($firmanavn) { $this->firmanavn = $firmanavn; }
    public function setTelefon($telefon) { $this->telefon = $telefon; }
    public function setEmail($email) { $this->email = $email; }
    public function setMomssats($momssats) { $this->momssats = $momssats; }
    public function setRef($ref) { $this->ref = $ref; }
    public function setStatus($status) { $this->status = $status; }
    public function setOrdrenr($ordrenr) { $this->ordrenr = $ordrenr; }
    public function setValutakurs($valutakurs) { $this->valutakurs = $valutakurs; }
    public function setValuta($valuta) { $this->valuta = $valuta; }
    public function setBetalingsbet($betalingsbet) { $this->betalingsbet = $betalingsbet; }
    public function setBetalingsdage($betalingsdage) { $this->betalingsdage = $betalingsdage; }
    public function setKontonr($kontonr) { $this->kontonr = $kontonr; }
    public function setSum($sum) { $this->sum = $sum; }
    public function setKostpris($kostpris) { $this->kostpris = $kostpris; }
    public function setMoms($moms) { $this->moms = $moms; }
    public function setBetalt($betalt) { $this->betalt = $betalt; }
    public function setOrdredate($ordredate) { $this->ordredate = $ordredate; }
    public function setNotes($notes) { $this->notes = $notes; }
    public function setArt($art) { $this->art = $art; }

    // Address setters
    public function setAddr1($addr1) { $this->addr1 = $addr1; }
    public function setAddr2($addr2) { $this->addr2 = $addr2; }
    public function setPostnr($postnr) { $this->postnr = $postnr; }
    public function setBynavn($bynavn) { $this->bynavn = $bynavn; }
    public function setLand($land) { $this->land = $land; }
    public function setKundeGruppe($kundegruppe) { $this->kundegruppe = $kundegruppe; }
    
    // Delivery address setters
    public function setLevNavn($lev_navn) { $this->lev_navn = $lev_navn; }
    public function setLevAddr1($lev_addr1) { $this->lev_addr1 = $lev_addr1; }
    public function setLevAddr2($lev_addr2) { $this->lev_addr2 = $lev_addr2; }
    public function setLevPostnr($lev_postnr) { $this->lev_postnr = $lev_postnr; }
    public function setLevBynavn($lev_bynavn) { $this->lev_bynavn = $lev_bynavn; }
    public function setLevLand($lev_land) { $this->lev_land = $lev_land; }
    
    // Other setters
    public function setEan($ean) { $this->ean = $ean; }
    public function setCvrnr($cvrnr) { $this->cvrnr = $cvrnr; }
}
