<?php

include_once __DIR__."/../lager/VareModel.php";
include_once __DIR__."/../lager/LagerModel.php";

/**
 * OrdreLinjeModel - Model for handling order lines in the system
 */
class OrdreLinjeModel
{
    // Properties to match database columns
    private $id;
    private $posnr;
    private $pris;
    private $rabat;
    private $ordre_id;
    private $vare_id;
    private $antal;
    private $leveres;
    private $leveret;
    private $bogf_konto;
    private $kred_linje_id;
    private $momsfri;
    private $kostpris;
    private $samlevare;
    private $rabatgruppe;
    private $m_rabat;
    private $folgevare;
    private $beskrivelse;
    private $bogfort_af;
    private $enhed;
    private $hvem;
    private $lev_varenr;
    private $oprettet_af;
    private $serienr;
    private $tidspkt;
    private $varenr;
    private $momssats;
    private $projekt;
    private $kdo;
    private $rabatart;
    private $variant_id;
    private $procent;
    private $omvbet;
    private $saet;
    private $fast_db;
    private $afd;
    private $lager_nr;
    private $vat_account;
    private $vat_price;
    
    // Vareobj
    private $vare;
    private $lager;

    /**
     * Constructor - can create an empty order line or load an existing one by ID
     * 
     * @param int|null $id Optional ID to load existing order line
     */
    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->loadFromId($id);
        }
    }

    /**
     * Load order line details from database by ID
     * 
     * @param int $id
     * @return bool Success status
     */
    private function loadFromId($id)
    {
        $qtxt = "SELECT * FROM ordrelinjer WHERE id = " . (int)$id;
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        if ($r = db_fetch_array($q)) {
            $this->id = (int)$r['id'];
            $this->posnr = isset($r['posnr']) ? (int)$r['posnr'] : null;
            $this->pris = isset($r['pris']) ? (float)$r['pris'] : null;
            $this->rabat = isset($r['rabat']) ? (float)$r['rabat'] : null;
            $this->ordre_id = isset($r['ordre_id']) ? (int)$r['ordre_id'] : null;
            $this->vare_id = isset($r['vare_id']) ? (int)$r['vare_id'] : null;
            $this->antal = isset($r['antal']) ? (float)$r['antal'] : null;
            $this->leveres = isset($r['leveres']) ? (float)$r['leveres'] : null;
            $this->leveret = isset($r['leveret']) ? (float)$r['leveret'] : null;
            $this->bogf_konto = isset($r['bogf_konto']) ? (int)$r['bogf_konto'] : null;
            $this->kred_linje_id = isset($r['kred_linje_id']) ? (int)$r['kred_linje_id'] : null;
            $this->momsfri = $r['momsfri'];
            $this->kostpris = isset($r['kostpris']) ? (float)$r['kostpris'] : null;
            $this->samlevare = $r['samlevare'];
            $this->rabatgruppe = isset($r['rabatgruppe']) ? (int)$r['rabatgruppe'] : null;
            $this->m_rabat = isset($r['m_rabat']) ? (float)$r['m_rabat'] : null;
            $this->folgevare = isset($r['folgevare']) ? (int)$r['folgevare'] : null;
            $this->beskrivelse = $r['beskrivelse'];
            $this->bogfort_af = $r['bogfort_af'];
            $this->enhed = $r['enhed'];
            $this->hvem = $r['hvem'];
            $this->lev_varenr = $r['lev_varenr'];
            $this->oprettet_af = $r['oprettet_af'];
            $this->serienr = $r['serienr'];
            $this->tidspkt = $r['tidspkt'];
            $this->varenr = $r['varenr'];
            $this->momssats = isset($r['momssats']) ? (float)$r['momssats'] : null;
            $this->projekt = $r['projekt'];
            $this->kdo = $r['kdo'];
            $this->rabatart = $r['rabatart'];
            $this->variant_id = $r['variant_id'];
            $this->procent = isset($r['procent']) ? (float)$r['procent'] : null;
            $this->omvbet = $r['omvbet'];
            $this->saet = isset($r['saet']) ? (int)$r['saet'] : null;
            $this->fast_db = isset($r['fast_db']) ? (float)$r['fast_db'] : null;
            $this->afd = isset($r['afd']) ? (int)$r['afd'] : null;
            $this->lager_nr = isset($r['lager']) ? (int)$r['lager'] : null;
            $this->vat_account = isset($r['vat_account']) ? (float)$r['vat_account'] : null;
            $this->vat_price = isset($r['vat_price']) ? (float)$r['vat_price'] : null;

            $this->vare = isset($r['vare_id']) ? new VareModel((int)$r['vare_id']) : null;
            $this->lager = isset($r['lager']) ? new LagerModel(null, (int)$r['lager'], (int)$r['vare_id']) : null;
            
            return true;
        }

        return false;
    }

    /**
     * Load order lines for a specific order
     * 
     * @param int $ordre_id
     * @return OrdreLinjeModel[] Array of order line objects
     */
    public static function loadLinesForOrder($ordre_id)
    {
        $qtxt = "SELECT id FROM ordrelinjer WHERE ordre_id = " . (int)$ordre_id . " ORDER BY posnr ASC";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $orderLines = [];
        while ($r = db_fetch_array($q)) {
            $orderLines[] = new OrdreLinjeModel($r['id']);
        }

        return $orderLines;
    }
    
    /**
     * Save/update the current order line
     * 
     * @return bool Success status
     */
    public function save()
    {
        // Escape string values
        $momsfri = db_escape_string($this->momsfri);
        $samlevare = db_escape_string($this->samlevare);
        $beskrivelse = db_escape_string($this->beskrivelse);
        $bogfort_af = db_escape_string($this->bogfort_af);
        $enhed = db_escape_string($this->enhed);
        $hvem = db_escape_string($this->hvem);
        $lev_varenr = db_escape_string($this->lev_varenr);
        $oprettet_af = db_escape_string($this->oprettet_af);
        $serienr = db_escape_string($this->serienr);
        $tidspkt = db_escape_string($this->tidspkt);
        $varenr = db_escape_string($this->varenr);
        $projekt = db_escape_string($this->projekt);
        $kdo = db_escape_string($this->kdo);
        $rabatart = db_escape_string($this->rabatart);
        $variant_id = db_escape_string($this->variant_id);
        $omvbet = db_escape_string($this->omvbet);

        if ($this->id) {
            // Update existing order line
            $qtxt = "UPDATE ordrelinjer SET 
                posnr = " . ($this->posnr !== null ? $this->posnr : "NULL") . ",
                pris = " . ($this->pris !== null ? $this->pris : "NULL") . ",
                rabat = " . ($this->rabat !== null ? $this->rabat : "NULL") . ",
                ordre_id = " . ($this->ordre_id !== null ? $this->ordre_id : "NULL") . ",
                vare_id = " . ($this->vare_id !== null ? $this->vare_id : "NULL") . ",
                antal = " . ($this->antal !== null ? $this->antal : "NULL") . ",
                leveres = " . ($this->leveres !== null ? $this->leveres : "NULL") . ",
                leveret = " . ($this->leveret !== null ? $this->leveret : "NULL") . ",
                bogf_konto = " . ($this->bogf_konto !== null ? $this->bogf_konto : "NULL") . ",
                kred_linje_id = " . ($this->kred_linje_id !== null ? $this->kred_linje_id : "NULL") . ",
                momsfri = '$momsfri',
                kostpris = " . ($this->kostpris !== null ? $this->kostpris : "NULL") . ",
                samlevare = '$samlevare',
                rabatgruppe = " . ($this->rabatgruppe !== null ? $this->rabatgruppe : "NULL") . ",
                m_rabat = " . ($this->m_rabat !== null ? $this->m_rabat : "NULL") . ",
                folgevare = " . ($this->folgevare !== null ? $this->folgevare : "NULL") . ",
                beskrivelse = '$beskrivelse',
                bogfort_af = '$bogfort_af',
                enhed = '$enhed',
                hvem = '$hvem',
                lev_varenr = '$lev_varenr',
                oprettet_af = '$oprettet_af',
                serienr = '$serienr',
                tidspkt = '$tidspkt',
                varenr = '$varenr',
                momssats = " . ($this->momssats !== null ? $this->momssats : "NULL") . ",
                projekt = '$projekt',
                kdo = '$kdo',
                rabatart = '$rabatart',
                variant_id = '$variant_id',
                procent = " . ($this->procent !== null ? $this->procent : "NULL") . ",
                omvbet = '$omvbet',
                saet = " . ($this->saet !== null ? $this->saet : "NULL") . ",
                fast_db = " . ($this->fast_db !== null ? $this->fast_db : "NULL") . ",
                afd = " . ($this->afd !== null ? $this->afd : "NULL") . ",
                lager = " . ($this->lager_nr !== null ? $this->lager_nr : "NULL") . ",
                vat_account = " . ($this->vat_account !== null ? $this->vat_account : "NULL") . ",
                vat_price = " . ($this->vat_price !== null ? $this->vat_price : "NULL") . "
                WHERE id = $this->id";

            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $success = explode("\t", $q)[0] == "0";
            
            return $success;
        } else {
            // Insert new order line
            $qtxt = "INSERT INTO ordrelinjer (
                posnr, pris, rabat, ordre_id, vare_id, antal, leveres, leveret, bogf_konto,
                kred_linje_id, momsfri, kostpris, samlevare, rabatgruppe, m_rabat, folgevare,
                beskrivelse, bogfort_af, enhed, hvem, lev_varenr, oprettet_af, serienr,
                tidspkt, varenr, momssats, projekt, kdo, rabatart, variant_id, procent,
                omvbet, saet, fast_db, afd, lager, vat_account, vat_price
            ) VALUES (
                " . ($this->posnr !== null ? $this->posnr : "NULL") . ",
                " . ($this->pris !== null ? $this->pris : "NULL") . ",
                " . ($this->rabat !== null ? $this->rabat : "NULL") . ",
                " . ($this->ordre_id !== null ? $this->ordre_id : "NULL") . ",
                " . ($this->vare_id !== null ? $this->vare_id : "NULL") . ",
                " . ($this->antal !== null ? $this->antal : "NULL") . ",
                " . ($this->leveres !== null ? $this->leveres : "NULL") . ",
                " . ($this->leveret !== null ? $this->leveret : "NULL") . ",
                " . ($this->bogf_konto !== null ? $this->bogf_konto : "NULL") . ",
                " . ($this->kred_linje_id !== null ? $this->kred_linje_id : "NULL") . ",
                '$momsfri',
                " . ($this->kostpris !== null ? $this->kostpris : "NULL") . ",
                '$samlevare',
                " . ($this->rabatgruppe !== null ? $this->rabatgruppe : "NULL") . ",
                " . ($this->m_rabat !== null ? $this->m_rabat : "NULL") . ",
                " . ($this->folgevare !== null ? $this->folgevare : "NULL") . ",
                '$beskrivelse',
                '$bogfort_af',
                '$enhed',
                '$hvem',
                '$lev_varenr',
                '$oprettet_af',
                '$serienr',
                '$tidspkt',
                '$varenr',
                " . ($this->momssats !== null ? $this->momssats : "NULL") . ",
                '$projekt',
                '$kdo',
                '$rabatart',
                '$variant_id',
                " . ($this->procent !== null ? $this->procent : "NULL") . ",
                '$omvbet',
                " . ($this->saet !== null ? $this->saet : "NULL") . ",
                " . ($this->fast_db !== null ? $this->fast_db : "NULL") . ",
                " . ($this->afd !== null ? $this->afd : "NULL") . ",
                " . ($this->lager_nr !== null ? $this->lager_nr : "NULL") . ",
                " . ($this->vat_account !== null ? $this->vat_account : "NULL") . ",
                " . ($this->vat_price !== null ? $this->vat_price : "NULL") . "
            )";

            $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            if (explode("\t", $q)[0] == "0") {
                // Get the ID of the newly inserted record
                $qtxt = "SELECT lastval() as last_id";
                $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
                if ($r = db_fetch_array($q)) {
                    $this->id = (int)$r['last_id'];
                    return true;
                }
            }
            
            return false;
        }
    }
    
    /**
     * Delete the current order line
     * 
     * @return bool Success status
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }
        
        $qtxt = "DELETE FROM ordrelinjer WHERE id = " . $this->id;
        $q = db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        return explode("\t", $q)[0] == "0";
    }
    
    /**
     * Calculate the line total (price * quantity - discount)
     * 
     * @return float
     */
    public function getLineTotal()
    {
        $lineTotal = 0;
        
        if ($this->antal && $this->pris) {
            $lineTotal = $this->antal * $this->pris;
            
            // Apply discount if available
            if ($this->rabat) {
                // Check discount type
                if ($this->rabatart == 'procent') {
                    // Percentage discount
                    $lineTotal = $lineTotal * (1 - ($this->rabat / 100));
                } else {
                    // Absolute amount discount
                    $lineTotal = $lineTotal - $this->rabat;
                }
            }
        }
        
        return $lineTotal;
    }
    
    /**
     * Get VAT amount for this line
     * 
     * @return float
     */
    public function getVatAmount()
    {
        // Check if line is VAT exempt
        if ($this->momsfri == 'on') {
            return 0;
        }
        
        $lineTotal = $this->getLineTotal();
        
        // Use line-specific VAT rate if available, otherwise 0
        $vatRate = $this->momssats ?: 0;
        
        return $lineTotal * ($vatRate / 100);
    }
    
    /**
     * Get remaining quantity to be delivered
     * 
     * @return float
     */
    public function getRemainingQuantity()
    {
        if ($this->antal === null) {
            return 0;
        }
        
        $remaining = $this->antal;
        
        if ($this->leveret !== null) {
            $remaining -= $this->leveret;
        }
        
        return max(0, $remaining);
    }
    
    /**
     * Convert order line to array representation
     * 
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'posnr' => $this->posnr,
            'ordre_id' => $this->ordre_id,
            'vare_id' => $this->vare_id,
            'varenr' => $this->varenr,
            'beskrivelse' => $this->beskrivelse,
            'antal' => array(
                'antal' => $this->antal,
                'enhed' => $this->enhed,
            ),
            'rabat' => array(
                'rabat' => $this->rabat,
                'rabatart' => $this->rabatart === "" ? "procent" : $this->rabatart,
            ),
            'priser' => array(
                'lineTotal' => $this->getLineTotal(),
                'kostpris' => $this->kostpris,
                'momsfri' => $this->momsfri == "on",
                'moms' => $this->getVatAmount(),
                'db' => $this->getLineTotal() - ($this->kostpris * $this->antal),
                'pris' => $this->pris,
            ),
            'logistics' => array(
                'leveres' => $this->leveres,
                'leveret' => $this->leveret,
                'remaining' => $this->getRemainingQuantity(),
            ),
            'lager_nr' => $this->lager_nr,
            'lager' => $this->lager->toArray(),
            'vare_ref' => $this->vare->toArray(),
        ];
    }

    // Getter methods
    public function getId() { return $this->id; }
    public function getPosnr() { return $this->posnr; }
    public function getPris() { return $this->pris; }
    public function getRabat() { return $this->rabat; }
    public function getOrdreId() { return $this->ordre_id; }
    public function getVareId() { return $this->vare_id; }
    public function getAntal() { return $this->antal; }
    public function getLeveres() { return $this->leveres; }
    public function getLeveret() { return $this->leveret; }
    public function getBogfKonto() { return $this->bogf_konto; }
    public function getKredLinjeId() { return $this->kred_linje_id; }
    public function getMomsfri() { return $this->momsfri; }
    public function getKostpris() { return $this->kostpris; }
    public function getSamlevare() { return $this->samlevare; }
    public function getRabatgruppe() { return $this->rabatgruppe; }
    public function getMRabat() { return $this->m_rabat; }
    public function getFolgevare() { return $this->folgevare; }
    public function getBeskrivelse() { return $this->beskrivelse; }
    public function getBogfortAf() { return $this->bogfort_af; }
    public function getEnhed() { return $this->enhed; }
    public function getHvem() { return $this->hvem; }
    public function getLevVarenr() { return $this->lev_varenr; }
    public function getOprettetAf() { return $this->oprettet_af; }
    public function getSerienr() { return $this->serienr; }
    public function getTidspkt() { return $this->tidspkt; }
    public function getVarenr() { return $this->varenr; }
    public function getMomssats() { return $this->momssats; }
    public function getProjekt() { return $this->projekt; }
    public function getKdo() { return $this->kdo; }
    public function getRabatart() { return $this->rabatart; }
    public function getVariantId() { return $this->variant_id; }
    public function getProcent() { return $this->procent; }
    public function getOmvbet() { return $this->omvbet; }
    public function getSaet() { return $this->saet; }
    public function getFastDb() { return $this->fast_db; }
    public function getAfd() { return $this->afd; }
    public function getLagerNr() { return $this->lager_nr; }
    public function getVatAccount() { return $this->vat_account; }
    public function getVatPrice() { return $this->vat_price; }

    // Setter methods
    public function setPosnr($posnr) { $this->posnr = $posnr; return $this; }
    public function setPris($pris) { $this->pris = $pris; return $this; }
    public function setRabat($rabat) { $this->rabat = $rabat; return $this; }
    public function setOrdreId($ordre_id) { $this->ordre_id = $ordre_id; return $this; }
    public function setVareId($vare_id) { $this->vare_id = $vare_id; return $this; }
    public function setAntal($antal) { $this->antal = $antal; return $this; }
    public function setLeveres($leveres) { $this->leveres = $leveres; return $this; }
    public function setLeveret($leveret) { $this->leveret = $leveret; return $this; }
    public function setBogfKonto($bogf_konto) { $this->bogf_konto = $bogf_konto; return $this; }
    public function setKredLinjeId($kred_linje_id) { $this->kred_linje_id = $kred_linje_id; return $this; }
    public function setMomsfri($momsfri) { $this->momsfri = $momsfri; return $this; }
    public function setKostpris($kostpris) { $this->kostpris = $kostpris; return $this; }
    public function setSamlevare($samlevare) { $this->samlevare = $samlevare; return $this; }
    public function setRabatgruppe($rabatgruppe) { $this->rabatgruppe = $rabatgruppe; return $this; }
    public function setMRabat($m_rabat) { $this->m_rabat = $m_rabat; return $this; }
    public function setFolgevare($folgevare) { $this->folgevare = $folgevare; return $this; }
    public function setBeskrivelse($beskrivelse) { $this->beskrivelse = $beskrivelse; return $this; }
    public function setBogfortAf($bogfort_af) { $this->bogfort_af = $bogfort_af; return $this; }
    public function setEnhed($enhed) { $this->enhed = $enhed; return $this; }
    public function setHvem($hvem) { $this->hvem = $hvem; return $this; }
    public function setLevVarenr($lev_varenr) { $this->lev_varenr = $lev_varenr; return $this; }
    public function setOprettetAf($oprettet_af) { $this->oprettet_af = $oprettet_af; return $this; }
    public function setSerienr($serienr) { $this->serienr = $serienr; return $this; }
    public function setTidspkt($tidspkt) { $this->tidspkt = $tidspkt; return $this; }
    public function setVarenr($varenr) { $this->varenr = $varenr; return $this; }
    public function setMomssats($momssats) { $this->momssats = $momssats; return $this; }
    public function setProjekt($projekt) { $this->projekt = $projekt; return $this; }
    public function setKdo($kdo) { $this->kdo = $kdo; return $this; }
    public function setRabatart($rabatart) { $this->rabatart = $rabatart; return $this; }
    public function setVariantId($variant_id) { $this->variant_id = $variant_id; return $this; }
    public function setProcent($procent) { $this->procent = $procent; return $this; }
    public function setOmvbet($omvbet) { $this->omvbet = $omvbet; return $this; }
    public function setSaet($saet) { $this->saet = $saet; return $this; }
    public function setFastDb($fast_db) { $this->fast_db = $fast_db; return $this; }
    public function setAfd($afd) { $this->afd = $afd; return $this; }
    public function setLagerNr($lager) { $this->lager_nr = $lager; return $this; }
    public function setVatAccount($vat_account) { $this->vat_account = $vat_account; return $this; }
    public function setVatPrice($vat_price) { $this->vat_price = $vat_price; return $this; }
}