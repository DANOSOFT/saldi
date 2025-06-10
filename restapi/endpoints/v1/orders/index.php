<?php

require_once '../../../models/ordre/OrdreModel.php';
require_once '../../../core/BaseEndpoint.php';

class OrdreEndpoint extends BaseEndpoint
{
    protected $model;

    public function __construct()
    {
        #parent::__construct($db);
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $ordre = new OrdreModel($id);
            if ($ordre->getId()) {
                $item = $ordre->toArray();
                $this->sendResponse(true, $item);
            } else {
                $this->sendResponse(false, null, "Order not found", 404);
            }
        } else {
            // Get all items and convert each to an array
            $orders = OrdreModel::getAllOrders();
            $items = [];
            foreach ($orders as $order) {
                $items[] = $order->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }

    protected function handlePost($data)
    {
        try {
            // Validate required fields
            $this->validateOrderData($data);

            // Create new order
            $ordre = new OrdreModel();
            
            // Set basic order information
            if (isset($data->konto_id)) $ordre->setKontoId($data->konto_id);
            if (isset($data->firmanavn)) $ordre->setFirmanavn($data->firmanavn);
            if (isset($data->kontakt)) $ordre->setKontakt($data->kontakt);
            if (isset($data->email)) $ordre->setEmail($data->email);
            if (isset($data->mail_fakt)) $ordre->setMailFakt($data->mail_fakt);
            if (isset($data->kundeordnr)) $ordre->setKundeordnr($data->kundeordnr);
            if (isset($data->ean)) $ordre->setEan($data->ean);
            if (isset($data->institution)) $ordre->setInstitution($data->institution);
            if (isset($data->kontonr)) $ordre->setKontonr($data->kontonr);
            if (isset($data->cvrnr)) $ordre->setCvrnr($data->cvrnr);
            if (isset($data->valuta)) $ordre->setValuta($data->valuta);
            if (isset($data->valutakurs)) $ordre->setValutakurs($data->valutakurs);
            if (isset($data->sprog)) $ordre->setSprog($data->sprog);
            if (isset($data->ordredate)) $ordre->setOrdredate($data->ordredate);
            if (isset($data->levdate)) $ordre->setLevdate($data->levdate);
            if (isset($data->notes)) $ordre->setNotes($data->notes);
            if (isset($data->momssats)) $ordre->setMomssats($data->momssats);
            if (isset($data->status)) $ordre->setStatus($data->status);
            if (isset($data->ref)) $ordre->setRef($data->ref);
            if (isset($data->sum)) $ordre->setSum($data->sum);
            if (isset($data->momsfri)) $ordre->setMomsfri($data->momsfri);
            if (isset($data->momsbelob)) $ordre->setMomsbelob($data->momsbelob);
            if (isset($data->faktureringsadresse)) $ordre->setFaktureringsadresse($data->faktureringsadresse);
            if (isset($data->leveringsadresse)) $ordre->setLeveringsadresse($data->leveringsadresse);

            // Generate order number if not provided
            if (isset($data->ordrenr)) {
                $ordre->setOrdrenr($data->ordrenr);
            } else {
                $ordre->setOrdrenr($this->generateOrderNumber());
            }

            // Set addresses if provided
            if (isset($data->adresse)) {
                $ordre->setAdresse($this->createAddressFromData($data->adresse));
            }
            if (isset($data->lev_adresse)) {
                $ordre->setLevAdresse($this->createAddressFromData($data->lev_adresse));
            }

            // Save the order first to get an ID
            $saveResult = $ordre->save();
            if ($saveResult !== true) {
                throw new Exception("Failed to save order: " . (is_string($saveResult) ? $saveResult : "Unknown error"));
            }

            // Add order lines if provided
            if (isset($data->orderLines) && is_array($data->orderLines)) {
                foreach ($data->orderLines as $lineData) {
                    $this->addOrderLine($ordre, $lineData);
                }
                
                // Recalculate totals after adding all lines
                $ordre->calculateTotals();
                $ordre->save();
            }

            // Return the created order
            $createdOrder = $ordre->toArray();
            $this->sendResponse(true, $createdOrder, "Order created successfully", 201);

        } catch (Exception $e) {
            $this->sendResponse(false, null, $e->getMessage(), 400);
        }
    }

    /**
     * Validate order data
     */
    private function validateOrderData($data)
    {
        $requiredFields = ['firmanavn']; // Minimum required field
        
        foreach ($requiredFields as $field) {
            if (!isset($data->$field) || empty($data->$field)) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate order lines if provided
        if (isset($data->orderLines)) {
            if (!is_array($data->orderLines)) {
                throw new Exception("orderLines must be an array");
            }
            
            foreach ($data->orderLines as $index => $line) {
                if (!isset($line->vare_id) || !isset($line->antal) || !isset($line->pris)) {
                    throw new Exception("Order line $index missing required fields: vare_id, antal, pris");
                }
            }
        }
    }

    /**
     * Generate a new order number
     */
    private function generateOrderNumber()
    {
        // Get the highest existing order number and increment
        $qtxt = "SELECT MAX(ordrenr) as max_ordrenr FROM ordrer";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return ($r['max_ordrenr'] ?? 0) + 1;
        }
        
        return 1;
    }

    /**
     * Create address model from data
     */
    private function createAddressFromData($addressData)
    {
        $adresse = new AdresseModel([
            'addr1' => $addressData->addr1 ?? '',
            'addr2' => $addressData->addr2 ?? '',
            'postnr' => $addressData->postnr ?? '',
            'bynavn' => $addressData->bynavn ?? '',
            'land' => $addressData->land ?? '',
            'kontakt' => $addressData->kontakt ?? ''
        ]);
        
        return $adresse;
    }

    /**
     * Add order line to order
     */
    private function addOrderLine($ordre, $lineData)
    {
        $orderLine = new OrdreLinjeModel();
        
        // Set required fields
        $orderLine->setOrdreId($ordre->getId());
        $orderLine->setVareId($lineData->vare_id);
        $orderLine->setAntal($lineData->antal);
        $orderLine->setPris($lineData->pris);
        
        // Set optional fields
        if (isset($lineData->posnr)) $orderLine->setPosnr($lineData->posnr);
        if (isset($lineData->rabat)) $orderLine->setRabat($lineData->rabat);
        if (isset($lineData->rabatart)) $orderLine->setRabatart($lineData->rabatart);
        if (isset($lineData->leveres)) $orderLine->setLeveres($lineData->leveres);
        if (isset($lineData->beskrivelse)) $orderLine->setBeskrivelse($lineData->beskrivelse);
        if (isset($lineData->enhed)) $orderLine->setEnhed($lineData->enhed);
        if (isset($lineData->kostpris)) $orderLine->setKostpris($lineData->kostpris);
        if (isset($lineData->momsfri)) $orderLine->setMomsfri($lineData->momsfri);
        if (isset($lineData->momssats)) $orderLine->setMomssats($lineData->momssats);
        if (isset($lineData->lager_nr)) $orderLine->setLagerNr($lineData->lager_nr);
        
        // Generate position number if not provided
        if (!isset($lineData->posnr)) {
            $orderLine->setPosnr($this->getNextPositionNumber($ordre->getId()));
        }

        // Save the order line
        $saveResult = $orderLine->save();
        if (!$saveResult) {
            throw new Exception("Failed to save order line for vare_id: " . $lineData->vare_id);
        }
    }

    /**
     * Get next position number for order lines
     */
    private function getNextPositionNumber($ordreId)
    {
        $qtxt = "SELECT MAX(posnr) as max_posnr FROM ordrelinjer WHERE ordre_id = " . (int)$ordreId;
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return ($r['max_posnr'] ?? 0) + 1;
        }
        
        return 1;
    }
}

$endpoint = new OrdreEndpoint();
$endpoint->handleRequestMethod();