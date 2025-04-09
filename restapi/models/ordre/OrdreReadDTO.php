<?php
/**
 * OrderReadDTO - A lightweight Data Transfer Object for OrderModel
 * Includes only essential order information without related entities
 */
class OrderReadDTO
{
    // Essential properties
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

    /**
     * Constructor - Create a DTO from database row or OrdreModel
     *
     * @param array|OrdreModel $source Data source (DB row or OrdreModel)
     */
    public function __construct($source = null)
    {
        if ($source !== null) {
            if ($source instanceof OrdreModel) {
                $this->loadFromOrdreModel($source);
            } elseif (is_array($source)) {
                $this->loadFromArray($source);
            }
        }
    }

    /**
     * Load data from OrdreModel
     *
     * @param OrdreModel $ordre
     */
    private function loadFromOrdreModel(OrdreModel $ordre)
    {
        $this->id = $ordre->getId();
        $this->konto_id = $ordre->getKontoId();
        $this->firmanavn = $ordre->getFirmanavn();
        $this->kontakt = $ordre->getKontakt();
        $this->email = $ordre->getEmail();
        $this->mail_fakt = $ordre->getMailFakt();
        $this->kundeordnr = $ordre->getKundeordnr();
        $this->ean = $ordre->getEan();
        $this->institution = $ordre->getInstitution();
        $this->kontonr = $ordre->getKontonr();
        $this->cvrnr = $ordre->getCvrnr();
        $this->valuta = $ordre->getValuta();
        $this->valutakurs = $ordre->getValutakurs();
        $this->sprog = $ordre->getSprog();
        $this->ordredate = $ordre->getOrdredate();
        $this->levdate = $ordre->getLevdate();
        $this->fakturadate = $ordre->getFakturadate();
        $this->notes = $ordre->getNotes();
        $this->ordrenr = $ordre->getOrdrenr();
        $this->sum = $ordre->getSum();
        $this->momssats = $ordre->getMomssats();
        $this->status = $ordre->getStatus();
        $this->ref = $ordre->getRef();
        $this->fakturanr = $ordre->getFakturanr();
        $this->kostpris = $ordre->getKostpris();
        $this->moms = $ordre->getMoms();
        
        // For addresses, we could either keep them as objects or flatten them
        // Here I'll use the same approach as in OrdreModel->toArray()
        $orderData = $ordre->toArray();
        $this->adresse = $orderData['kunde']['adresse'];
        $this->lev_adresse = $orderData['lev_adresse'];
    }

    /**
     * Load data from database row
     *
     * @param array $row Database row
     */
    private function loadFromArray(array $row)
    {
        $this->id = isset($row['id']) ? (int)$row['id'] : null;
        $this->konto_id = isset($row['konto_id']) ? (int)$row['konto_id'] : null;
        $this->firmanavn = isset($row['firmanavn']) ? $row['firmanavn'] : null;
        $this->kontakt = isset($row['kontakt']) ? $row['kontakt'] : null;
        $this->email = isset($row['email']) ? $row['email'] : null;
        $this->mail_fakt = isset($row['mail_fakt']) ? $row['mail_fakt'] : null;
        $this->kundeordnr = isset($row['kundeordnr']) ? $row['kundeordnr'] : null;
        $this->ean = isset($row['ean']) ? $row['ean'] : null;
        $this->institution = isset($row['institution']) ? $row['institution'] : null;
        $this->kontonr = isset($row['kontonr']) ? $row['kontonr'] : null;
        $this->cvrnr = isset($row['cvrnr']) ? $row['cvrnr'] : null;
        $this->valuta = isset($row['valuta']) ? $row['valuta'] : null;
        $this->valutakurs = isset($row['valutakurs']) ? (float)$row['valutakurs'] : null;
        $this->sprog = isset($row['sprog']) ? $row['sprog'] : null;
        $this->ordredate = isset($row['ordredate']) ? $row['ordredate'] : null;
        $this->levdate = isset($row['levdate']) ? $row['levdate'] : null;
        $this->fakturadate = isset($row['fakturadate']) ? $row['fakturadate'] : null;
        $this->notes = isset($row['notes']) ? $row['notes'] : null;
        $this->ordrenr = isset($row['ordrenr']) ? (int)$row['ordrenr'] : null;
        $this->sum = isset($row['sum']) ? (float)$row['sum'] : null;
        $this->momssats = isset($row['momssats']) ? (float)$row['momssats'] : null;
        $this->status = isset($row['status']) ? (int)$row['status'] : null;
        $this->ref = isset($row['ref']) ? $row['ref'] : null;
        $this->fakturanr = isset($row['fakturanr']) ? $row['fakturanr'] : null;
        $this->kostpris = isset($row['kostpris']) ? (float)$row['kostpris'] : null;
        $this->moms = isset($row['moms']) ? (float)$row['moms'] : null;
        
        // Handle addresses as simple arrays
        $this->adresse = [
            'addr1' => isset($row['addr1']) ? $row['addr1'] : null,
            'addr2' => isset($row['addr2']) ? $row['addr2'] : null,
            'postnr' => isset($row['postnr']) ? $row['postnr'] : null,
            'bynavn' => isset($row['bynavn']) ? $row['bynavn'] : null,
            'land' => isset($row['land']) ? $row['land'] : null,
            'kontakt' => isset($row['kontakt']) ? $row['kontakt'] : null
        ];
        
        $this->lev_adresse = [
            'addr1' => isset($row['lev_addr1']) ? $row['lev_addr1'] : null,
            'addr2' => isset($row['lev_addr2']) ? $row['lev_addr2'] : null,
            'postnr' => isset($row['lev_postnr']) ? $row['lev_postnr'] : null,
            'bynavn' => isset($row['lev_bynavn']) ? $row['lev_bynavn'] : null,
            'land' => null, // Not implemented in the original model
            'kontakt' => isset($row['lev_kontakt']) ? $row['lev_kontakt'] : null
        ];
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'ordrenr' => $this->ordrenr,
            'kundeordnr' => $this->kundeordnr,
            'fakturanr' => $this->fakturanr,
            'kunde' => [
                'konto_id' => $this->konto_id,
                'kontonr' => $this->kontonr,
                'firmanavn' => $this->firmanavn,
                'cvrnr' => $this->cvrnr,
                'kontakt' => $this->kontakt,
                'email' => $this->email,
                'ean' => $this->ean,
                'adresse' => $this->adresse,
            ],
            'lev_adresse' => $this->lev_adresse,
            'mail_fakt' => $this->mail_fakt,
            'institution' => $this->institution,
            'valuta' => $this->valuta,
            'valutakurs' => $this->valutakurs,
            'sprog' => $this->sprog,
            'datoer' => [
                'ordredate' => $this->ordredate,
                'levdate' => $this->levdate,
                'fakturadate' => $this->fakturadate,
            ],
            'priser' => [
                'salgspris' => $this->sum,
                'kostpris' => $this->kostpris,
                'momssats' => $this->momssats,
                'moms' => $this->moms,
                'momspris' => $this->sum + $this->moms,
            ],
            'notes' => $this->notes,
            'status' => $this->status,
            'ref' => $this->ref,
        ];
    }

    /**
     * Create DTO from OrdreModel
     * 
     * @param OrdreModel $ordre
     * @return OrderReadDTO
     */
    public static function fromModel(OrdreModel $ordre)
    {
        return new self($ordre);
    }

    /**
     * Create collection of DTOs from array of OrdreModel objects
     * 
     * @param OrdreModel[] $ordreModels
     * @return OrderReadDTO[]
     */
    public static function fromModels(array $ordreModels)
    {
        $dtos = [];
        foreach ($ordreModels as $ordre) {
            if ($ordre instanceof OrdreModel) {
                $dtos[] = new self($ordre);
            }
        }
        return $dtos;
    }

    // Getters
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
    public function getAdresse() { return $this->adresse; }
    public function getLevAdresse() { return $this->lev_adresse; }
    
    /**
     * Get total amount including VAT
     * 
     * @return float
     */
    public function getTotalWithVAT()
    {
        return $this->sum + $this->moms;
    }
}