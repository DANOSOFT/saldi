<?php

include_once __DIR__."/AdressModel.php";
include_once __DIR__."/OrdreLinjeModel.php";
include_once __DIR__."/OrdreReadDTO.php";

/**
 * OrdreModel - Model for handling orders in the system
 */
class OrdreModel
{
    // Properties to match database columns
    private $id;
    private $konto_id;
    private $firmanavn;
    private $kontakt;
    private $email;
    private $mail_fakt;
    private $kundeordnr;
    private $ean;
    private $institution;
    private $kontonr;
    private $cvrnr;
    private $valuta;
    private $valutakurs;
    private $sprog;
    private $ordredate;
    private $levdate;
    private $fakturadate;
    private $notes;
    private $ordrenr;
    private $sum;
    private $momssats;
    private $status;
    private $ref;
    private $fakturanr;
    private $kostpris;
    private $moms;
    private $adresse;
    private $lev_adresse;

    // Order lines collection
    private $orderLines = [];

    /**
     * Constructor - can create an empty order or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing order
     */
    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->loadFromId($id);
        }
    }

    /**
     * Load order details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id)
    {
        $qtxt = "SELECT * FROM ordrer WHERE id = " . (int)$id;
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->konto_id = isset($r['konto_id']) ? (int)$r['konto_id'] : null;
            $this->firmanavn = $r['firmanavn'];
            $this->kontakt = $r['kontakt'];
            $this->email = $r['email'];
            $this->mail_fakt = $r['mail_fakt'];
            $this->kundeordnr = $r['kundeordnr'];
            $this->ean = $r['ean'];
            $this->institution = $r['institution'];
            $this->kontonr = $r['kontonr'];
            $this->cvrnr = $r['cvrnr'];
            $this->valuta = $r['valuta'];
            $this->valutakurs = isset($r['valutakurs']) ? (float)$r['valutakurs'] : null;
            $this->sprog = $r['sprog'];
            $this->ordredate = $r['ordredate'];
            $this->levdate = $r['levdate'];
            $this->fakturadate = $r['fakturadate'];
            $this->notes = $r['notes'];
            $this->ordrenr = isset($r['ordrenr']) ? (int)$r['ordrenr'] : null;
            $this->sum = isset($r['sum']) ? (float)$r['sum'] : null;
            $this->momssats = isset($r['momssats']) ? (float)$r['momssats'] : null;
            $this->status = isset($r['status']) ? (int)$r['status'] : null;
            $this->ref = $r['ref'];
            $this->fakturanr = $r['fakturanr'];
            $this->kostpris = isset($r['kostpris']) ? (float)$r['kostpris'] : null;
            $this->moms = isset($r['moms']) ? (float)$r['moms'] : null;

            $this->adresse = new AdresseModel(array(
                'addr1' => isset($r['addr1']) ? $r['addr1'] : '',
                'addr2' => isset($r['addr2']) ? $r['addr2'] : '',
                'postnr' => isset($r['postnr']) ? $r['postnr'] : '',
                'bynavn' => isset($r['bynavn']) ? $r['bynavn'] : '',
                'land' => isset($r['land']) ? $r['land'] : '',
                'kontakt' => isset($r['kontakt']) ? $r['kontakt'] : '',
            ));
            $this->lev_adresse = new AdresseModel(array(
                'addr1' => isset($r['lev_addr1']) ? $r['lev_addr1'] : '',
                'addr2' => isset($r['lev_addr2']) ? $r['lev_addr2'] : '',
                'postnr' => isset($r['lev_postnr']) ? $r['lev_postnr'] : '',
                'bynavn' => isset($r['lev_bynavn']) ? $r['lev_bynavn'] : '',
                'land' => null,  // Not implemented
                'kontakt' => isset($r['lev_kontakt']) ? $r['lev_kontakt'] : '',
            ));

            $this->orderLines = OrdreLinjeModel::loadLinesForOrder($this->id);
            
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
        // Format dates for SQL
        $ordredate = $this->ordredate ? "'" . date('Y-m-d', strtotime($this->ordredate)) . "'" : "NULL";
        $levdate = $this->levdate ? "'" . date('Y-m-d', strtotime($this->levdate)) . "'" : "NULL";
        $fakturadate = $this->fakturadate ? "'" . date('Y-m-d', strtotime($this->fakturadate)) . "'" : "NULL";
        
        // Escape string values
        $firmanavn = db_escape_string($this->firmanavn);
        $kontakt = db_escape_string($this->kontakt);
        $email = db_escape_string($this->email);
        $mail_fakt = db_escape_string($this->mail_fakt);
        $kundeordnr = db_escape_string($this->kundeordnr);
        $ean = db_escape_string($this->ean);
        $institution = db_escape_string($this->institution);
        $kontonr = db_escape_string($this->kontonr);
        $cvrnr = db_escape_string($this->cvrnr);
        $valuta = db_escape_string($this->valuta);
        $sprog = db_escape_string($this->sprog);
        $notes = db_escape_string($this->notes);
        $ref = db_escape_string($this->ref);
        $fakturanr = db_escape_string($this->fakturanr);

        if ($this->id) {
            // Update existing order
            $qtxt = "UPDATE ordrer SET 
                konto_id = " . ($this->konto_id !== null ? $this->konto_id : "NULL") . ",
                firmanavn = '$firmanavn',
                kontakt = '$kontakt',
                email = '$email',
                mail_fakt = '$mail_fakt',
                kundeordnr = '$kundeordnr',
                ean = '$ean',
                institution = '$institution',
                kontonr = '$kontonr',
                cvrnr = '$cvrnr',
                valuta = '$valuta',
                valutakurs = " . ($this->valutakurs !== null ? $this->valutakurs : "NULL") . ",
                sprog = '$sprog',
                ordredate = $ordredate,
                levdate = $levdate,
                fakturadate = $fakturadate,
                notes = '$notes',
                ordrenr = " . ($this->ordrenr !== null ? $this->ordrenr : "NULL") . ",
                sum = " . ($this->sum !== null ? $this->sum : "NULL") . ",
                momssats = " . ($this->momssats !== null ? $this->momssats : "NULL") . ",
                status = " . ($this->status !== null ? $this->status : "NULL") . ",
                ref = '$ref',
                fakturanr = '$fakturanr',
                kostpris = " . ($this->kostpris !== null ? $this->kostpris : "NULL") . ",
                moms = " . ($this->moms !== null ? $this->moms : "NULL") . "
                WHERE id = $this->id";

            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $success = explode("\t", $q)[0] == "0";
            
            return $success;
        } else {
            // Insert new order
            $qtxt = "INSERT INTO ordrer (
                konto_id, firmanavn, kontakt, email, mail_fakt, kundeordnr, ean, institution,
                kontonr, cvrnr, valuta, valutakurs, sprog, ordredate, levdate, fakturadate,
                notes, ordrenr, sum, momssats, status, ref, fakturanr, kostpris, moms
            ) VALUES (
                " . ($this->konto_id !== null ? $this->konto_id : "NULL") . ",
                '$firmanavn',
                '$kontakt',
                '$email',
                '$mail_fakt',
                '$kundeordnr',
                '$ean',
                '$institution',
                '$kontonr',
                '$cvrnr',
                '$valuta',
                " . ($this->valutakurs !== null ? $this->valutakurs : "NULL") . ",
                '$sprog',
                $ordredate,
                $levdate,
                $fakturadate,
                '$notes',
                " . ($this->ordrenr !== null ? $this->ordrenr : "NULL") . ",
                " . ($this->sum !== null ? $this->sum : "NULL") . ",
                " . ($this->momssats !== null ? $this->momssats : "NULL") . ",
                " . ($this->status !== null ? $this->status : "NULL") . ",
                '$ref',
                '$fakturanr',
                " . ($this->kostpris !== null ? $this->kostpris : "NULL") . ",
                " . ($this->moms !== null ? $this->moms : "NULL") . "
            )";

            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            return explode("\t", $q)[0] == "0";
        }
    }
    
    /**
     * Delete the current order and its order lines
     * 
     * @return bool Success status
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        // First delete all order lines
        $qtxt = "DELETE FROM ordrelinjer WHERE ordre_id = " . $this->id;
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        
        // Then delete the order
        $qtxt = "DELETE FROM ordrer WHERE id = " . $this->id;
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return explode("\t", $q)[0] == "0";
    }

    /**
     * Calculate totals for the order
     */
    public function calculateTotals()
    {
        $sum = 0;
        $kostpris = 0;
        
        foreach ($this->orderLines as $line) {
            $sum += $line->getLineTotal();
            $kostpris += $line->getKostpris() * $line->getAntal();
        }
        
        $this->sum = $sum;
        $this->kostpris = $kostpris;
        
        // Calculate VAT based on momssats
        if ($this->momssats > 0) {
            $this->moms = $sum * ($this->momssats / 100);
        } else {
            $this->moms = 0;
        }
    }

    /**
     * Get total amount including VAT
     * 
     * @return float
     */
    public function getTotalWithVAT()
    {
        return $this->sum + $this->moms;
    }
    
    /**
     * Remove an order line by its index
     * 
     * @param int $index
     * @return bool Success status
     */
    public function removeOrderLine($index)
    {
        if (isset($this->orderLines[$index])) {
            $line = $this->orderLines[$index];
            
            // If line has an ID, delete it from the database
            if ($line->getId()) {
                $line->delete();
            }
            
            // Remove from array
            array_splice($this->orderLines, $index, 1);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all order lines
     * 
     * @return OrdreLinjeModel[]
     */
    public function getOrderLines()
    {
        return $this->orderLines;
    }

    /**
     * Class method to get all orders
     * 
     * @param string $orderBy Column to order by (default: id)
     * @param string $orderDirection Sort direction (default: DESC)
     * @return OrdreModel[] Array of order objects
     */
    public static function getAllOrders($orderBy = 'id', $orderDirection = 'DESC')
    {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = [
            'id', 'konto_id', 'firmanavn', 'ordredate', 'levdate', 'fakturadate',
            'ordrenr', 'sum', 'status', 'fakturanr'
        ];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'id';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

        $qtxt = "SELECT * FROM ordrer ORDER BY $orderBy $orderDirection";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $orderDTOs = [];
        while ($r = db_fetch_array($q)) {
            // Create DTO directly from database row to avoid creating full models
            $orderDTOs[] = new OrderReadDTO($r);
        }

        return $orderDTOs;
    }
    
    /**
     * Class method to get orders with a specific status
     * 
     * @param int $status Status code to filter by
     * @param string $orderBy Column to order by (default: id)
     * @param string $orderDirection Sort direction (default: DESC)
     * @return OrdreModel[] Array of order objects
     */
    public static function getOrdersByStatus($status, $orderBy = 'id', $orderDirection = 'DESC')
    {
        // Whitelist allowed order by columns to prevent SQL injection
        $allowedOrderBy = [
            'id', 'konto_id', 'firmanavn', 'ordredate', 'levdate', 'fakturadate',
            'ordrenr', 'sum', 'status', 'fakturanr'
        ];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'id';

        // Validate order direction
        $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

        $qtxt = "SELECT id FROM ordrer WHERE status = " . (int)$status . " ORDER BY $orderBy $orderDirection";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $orders = [];
        while ($r = db_fetch_array($q)) {
            $orders[] = new OrdreModel($r['id']);
        }

        return $orders;
    }
    
    /**
     * Search for orders based on various criteria
     * 
     * @param array $criteria Search criteria
     * @return OrdreModel[] Array of matching order objects
     */
    public static function searchOrders($criteria = [])
    {
        $where = [];
        
        // Build WHERE clause based on provided criteria
        if (!empty($criteria['konto_id'])) {
            $where[] = "konto_id = " . (int)$criteria['konto_id'];
        }
        
        if (!empty($criteria['firmanavn'])) {
            $where[] = "firmanavn LIKE '%" . db_escape_string($criteria['firmanavn']) . "%'";
        }
        
        if (!empty($criteria['ordrenr'])) {
            $where[] = "ordrenr = " . (int)$criteria['ordrenr'];
        }
        
        if (!empty($criteria['fakturanr'])) {
            $where[] = "fakturanr = '" . db_escape_string($criteria['fakturanr']) . "'";
        }
        
        if (!empty($criteria['status'])) {
            $where[] = "status = " . (int)$criteria['status'];
        }
        
        if (!empty($criteria['from_date'])) {
            $from_date = date('Y-m-d', strtotime($criteria['from_date']));
            $where[] = "ordredate >= '$from_date'";
        }
        
        if (!empty($criteria['to_date'])) {
            $to_date = date('Y-m-d', strtotime($criteria['to_date']));
            $where[] = "ordredate <= '$to_date'";
        }
        
        // Build the query
        $qtxt = "SELECT id FROM ordrer";
        
        if (!empty($where)) {
            $qtxt .= " WHERE " . implode(" AND ", $where);
        }
        
        $qtxt .= " ORDER BY ordredate DESC, id DESC";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $orders = [];
        while ($r = db_fetch_array($q)) {
            $orders[] = new OrdreModel($r['id']);
        }

        return $orders;
    }

    /**
     * Convert order to array representation
     * 
     * @return array
     */
    public function toArray()
    {
        $orderLines = [];
        foreach ($this->orderLines as $line) {
            $orderLines[] = $line->toArray();
        }
        
        return [
            'id' => $this->id,
            'ordrenr' => $this->ordrenr,
            'kundeordnr' => $this->kundeordnr,
            'fakturanr' => $this->fakturanr,
            'kunde' => array(
                'konto_id' => $this->konto_id,
                'kontonr' => $this->kontonr,
                'firmanavn' => $this->firmanavn,
                'cvrnr' => $this->cvrnr,
                'kontakt' => $this->kontakt,
                'email' => $this->email,
                'ean' => $this->ean,
                'adresse' => $this->adresse->toArray(),
            ),
            'lev_adresse' => $this->lev_adresse->toArray(),
            'mail_fakt' => $this->mail_fakt,
            'institution' => $this->institution,
            'valuta' => $this->valuta,
            'valutakurs' => $this->valutakurs,
            'sprog' => $this->sprog,
            'datoer' => array(
                'ordredate' => $this->ordredate,
                'levdate' => $this->levdate,
                'fakturadate' => $this->fakturadate,
            ),
            'priser' => array(
                'salgspris' => $this->sum,
                'kostpris' => $this->kostpris,
                'momssats' => $this->momssats,
                'moms' => $this->moms,
                'momspris' => $this->getTotalWithVAT(),
            ),
            'notes' => $this->notes,
            'status' => $this->status,
            'ref' => $this->ref,
            'orderLines' => $orderLines
        ];
    }

    // Getter methods
    public function getId() { return $this->id; }
    public function getKontoId() { return $this->konto_id; }
    public function getFirmanavn() { return $this->firmanavn; }
    public function getKontakt() { return $this->kontakt; }
    public function getEmail() { return $this->email; }
    public function getMailFakt() { return $this->mail_fakt; }
    public function getKundeordnr() { return $this->kundeordnr; }
    public function getEan() { return $this->ean; }
    public function getInstitution() { return $this->institution; }
    public function getKontonr() { return $this->kontonr; }
    public function getCvrnr() { return $this->cvrnr; }
    public function getValuta() { return $this->valuta; }
    public function getValutakurs() { return $this->valutakurs; }
    public function getSprog() { return $this->sprog; }
    public function getOrdredate() { return $this->ordredate; }
    public function getLevdate() { return $this->levdate; }
    public function getFakturadate() { return $this->fakturadate; }
    public function getNotes() { return $this->notes; }
    public function getOrdrenr() { return $this->ordrenr; }
    public function getSum() { return $this->sum; }
    public function getMomssats() { return $this->momssats; }
    public function getMoms() { return $this->moms; }
    public function getStatus() { return $this->status; }
    public function getRef() { return $this->ref; }
    public function getFakturanr() { return $this->fakturanr; }
    public function getKostpris() { return $this->kostpris; }

    // Setter methods
    public function setKontoId($konto_id) { $this->konto_id = $konto_id; return $this; }
    public function setFirmanavn($firmanavn) { $this->firmanavn = $firmanavn; return $this; }
    public function setKontakt($kontakt) { $this->kontakt = $kontakt; return $this; }
    public function setEmail($email) { $this->email = $email; return $this; }
    public function setMailFakt($mail_fakt) { $this->mail_fakt = $mail_fakt; return $this; }
    public function setKundeordnr($kundeordnr) { $this->kundeordnr = $kundeordnr; return $this; }
    public function setEan($ean) { $this->ean = $ean; return $this; }
    public function setInstitution($institution) { $this->institution = $institution; return $this; }
    public function setKontonr($kontonr) { $this->kontonr = $kontonr; return $this; }
    public function setCvrnr($cvrnr) { $this->cvrnr = $cvrnr; return $this; }
    public function setValuta($valuta) { $this->valuta = $valuta; return $this; }
    public function setValutakurs($valutakurs) { $this->valutakurs = $valutakurs; return $this; }
    public function setSprog($sprog) { $this->sprog = $sprog; return $this; }
    public function setOrdredate($ordredate) { $this->ordredate = $ordredate; return $this; }
    public function setLevdate($levdate) { $this->levdate = $levdate; return $this; }
    public function setFakturadate($fakturadate) { $this->fakturadate = $fakturadate; return $this; }
    public function setNotes($notes) { $this->notes = $notes; return $this; }
    public function setOrdrenr($ordrenr) { $this->ordrenr = $ordrenr; return $this; }
    public function setSum($sum) { $this->sum = $sum; return $this; }
    public function setMomssats($momssats) { $this->momssats = $momssats; return $this; }
    public function setMoms($moms) { $this->moms = $moms; return $this; }
    public function setStatus($status) { $this->status = $status; return $this; }
    public function setRef($ref) { $this->ref = $ref; return $this; }
    public function setFakturanr($fakturanr) { $this->fakturanr = $fakturanr; return $this; }
    public function setKostpris($kostpris) { $this->kostpris = $kostpris; return $this; }
}