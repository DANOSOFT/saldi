<?php

require_once __DIR__ . '/../models/orders/OrderModel.php';

class OrderService
{
    /**
     * Create a new order with validation and business logic
     * 
     * @param object $data Order data from request
     * @return array Result with success status and data/message
     */
    public static function createOrder($data)
    {
        // Validate required fields
        $required = ['firmanavn', 'telefon', 'email', 'momssats'];
        foreach ($required as $field) {
            if (!isset($data->$field) || empty($data->$field)) {
                return ['success' => false, 'message' => "Required field missing: $field"];
            }
        }

        $order = new OrderModel();
        
        // Set required fields
        $order->setFirmanavn($data->firmanavn);
        $order->setTelefon($data->telefon);
        $order->setEmail($data->email);
        $order->setMomssats($data->momssats);

        // Check if user exists by phone number and get/create konto_id and kontonr
        $debtorInfo = self::getOrCreateDebtor($data);
        if ($debtorInfo === false) {
            return ['success' => false, 'message' => 'Failed to create or find debtor'];
        }
        
        $order->setKontoId($debtorInfo['id']);
        $order->setKontonr($debtorInfo['kontonr']);
        
        // Use existing user payment terms or set defaults
        $order->setBetalingsbet(isset($data->betalingsbet) ? $data->betalingsbet : $debtorInfo['betalingsbet']);
        $order->setBetalingsdage(isset($data->betalingsdage) ? $data->betalingsdage : $debtorInfo['betalingsdage']);

        // Set optional fields
        if (isset($data->sum)) $order->setSum($data->sum);
        if (isset($data->kostpris)) $order->setKostpris($data->kostpris);
        if (isset($data->moms)) $order->setMoms($data->moms);
        
        // Handle currency
        $valuta = self::getValuta($data);
        $order->setValuta($valuta);
        
        // Get exchange rate
        $valutakurs = self::getValutakurs($valuta);
        if ($valutakurs === false) {
            return ['success' => false, 'message' => "Currency exchange rate not found for: $valuta"];
        }
        $order->setValutakurs($valutakurs);

        // Set address fields
        if (isset($data->addr1)) $order->setAddr1($data->addr1);
        if (isset($data->addr2)) $order->setAddr2($data->addr2);
        if (isset($data->postnr)) $order->setPostnr($data->postnr);
        if (isset($data->bynavn)) $order->setBynavn($data->bynavn);
        if (isset($data->land)) $order->setLand($data->land);

        // Set delivery fields
        if (isset($data->lev_navn)) $order->setLevNavn($data->lev_navn);
        if (isset($data->lev_addr1)) $order->setLevAddr1($data->lev_addr1);
        if (isset($data->lev_addr2)) $order->setLevAddr2($data->lev_addr2);
        if (isset($data->lev_postnr)) $order->setLevPostnr($data->lev_postnr);
        if (isset($data->lev_bynavn)) $order->setLevBynavn($data->lev_bynavn);
        if (isset($data->lev_land)) $order->setLevLand($data->lev_land);

        // Set other fields
        if (isset($data->ean)) $order->setEan($data->ean);
        if (isset($data->cvrnr)) $order->setCvrnr($data->cvrnr);
        if (isset($data->ordredate)) $order->setOrdredate($data->ordredate);
        if (isset($data->notes)) $order->setNotes($data->notes);

        // Handle betalt field
        $betalt = (isset($data->betalt) && $data->betalt) ? 'on' : '';
        $order->setBetalt($betalt);

        // Set system fields
        $order->setRef(self::getSaldiUser());
        $order->setStatus(0);
        $order->setOrdrenr(self::getNextOrderNumber());

        // Save the order
        if ($order->save()) {
            return ['success' => true, 'data' => $order->toArray()];
        } else {
            return ['success' => false, 'message' => 'Failed to save order'];
        }
    }

    /**
     * Get existing debtor or create new one if not found
     * According to ordrer.txt: check tlf in adresser, if exists use id and kontonr, if not create new
     * 
     * @param object $data Order data
     * @return array|false Debtor info (id, kontonr, betalingsbet, betalingsdage) or false on error
     */
    private static function getOrCreateDebtor($data)
    {
        // First, check if user exists by phone number
        $existingDebtor = self::getUserByPhone($data->telefon);
        
        if ($existingDebtor) {
            // User exists, return their info
            return [
                'id' => $existingDebtor['id'],
                'kontonr' => $existingDebtor['kontonr'],
                'betalingsbet' => $existingDebtor['betalingsbet'] ?: 'netto',
                'betalingsdage' => $existingDebtor['betalingsdage'] ?: 8
            ];
        } else {
            // User doesn't exist, create new debtor
            return self::createNewDebtor($data);
        }
    }

    /**
     * Get user by phone number from adresser table
     * 
     * @param string $phone Phone number
     * @return array|null User data or null if not found
     */
    private static function getUserByPhone($phone)
    {
        $qtxt = "SELECT id, kontonr, betalingsbet, betalingsdage FROM adresser WHERE tlf = '$phone'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return $r;
        }
        
        return null;
    }

    /**
     * Create new debtor in adresser table
     * 
     * @param object $data Order data
     * @return array|false New debtor info or false on error
     */
    private static function createNewDebtor($data)
    {
        // Get next kontonr
        $nextKontonr = self::getNextKontonr();
        if ($nextKontonr === false) {
            return false;
        }

        // Prepare address data with defaults
        $addr1 = isset($data->addr1) ? $data->addr1 : '';
        $addr2 = isset($data->addr2) ? $data->addr2 : '';
        $postnr = isset($data->postnr) ? $data->postnr : '';
        $bynavn = isset($data->bynavn) ? $data->bynavn : '';
        $land = isset($data->land) ? $data->land : '';
        $ean = isset($data->ean) ? $data->ean : '';
        $cvrnr = isset($data->cvrnr) ? $data->cvrnr : '';

        // Handle kundegruppe - default to 1 if not provided
        $kundegruppe = (isset($data->kundegruppe) && $data->kundegruppe != "") ? $data->kundegruppe : 1;

        // Insert new debitor
        $qtxt = "INSERT INTO adresser (
            firmanavn, tlf, email, addr1, addr2, postnr, bynavn, land, 
            ean, cvrnr, kontonr, betalingsbet, betalingsdage, art, gruppe
        ) VALUES (
            '$data->firmanavn', '$data->telefon', '$data->email', '$addr1', '$addr2', 
            '$postnr', '$bynavn', '$land', '$ean', '$cvrnr', '$nextKontonr', 'netto', 8, 'D', '$kundegruppe'
        )";

        $result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        
        if (explode("\t", $result)[0] == "0") {
            // Success, get the new ID
            $newId = self::getLastInsertId();
            if ($newId) {
                return [
                    'id' => $newId,
                    'kontonr' => $nextKontonr,
                    'betalingsbet' => 'netto',
                    'betalingsdage' => 8
                ];
            }
        }
        
        return false;
    }

    /**
     * Get next available kontonr
     * 
     * @return int|false Next kontonr or false on error
     */
    private static function getNextKontonr()
    {
        $qtxt = "SELECT MAX(kontonr) as max_kontonr FROM adresser";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return (int)$r['max_kontonr'] + 1;
        }

        return 1000; // Default starting kontonr if no records exist
    }

    /**
     * Get last inserted ID
     * 
     * @return int|false Last insert ID or false on error
     */
    private static function getLastInsertId()
    {
        $qtxt = "SELECT LASTVAL() as last_id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return (int)$r['last_id'];
        }
        
        return false;
    }

    /**
     * Get currency from input or settings
     * 
     * @param object $data Input data
     * @return string Currency code
     */
    private static function getValuta($data)
    {
        if (isset($data->valuta) && !empty($data->valuta)) {
            return $data->valuta;
        }

        // Check settings for base currency
        $qtxt = "SELECT var_value FROM settings WHERE var_name = 'baseCurrency'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return $r['var_value'];
        }

        return 'DKK'; // Default fallback
    }

    /**
     * Get exchange rate for currency
     * 
     * @param string $valuta Currency code
     * @return float|false Exchange rate or false if not found
     */
    private static function getValutakurs($valuta)
    {
        $qtxt = "SELECT var_value FROM settings WHERE var_name = 'baseCurrency'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($r = db_fetch_array($q)) {
            $baseCurrency = $r['var_value'];
        }else{
            $baseCurrency = 'DKK'; // Default base currency if not set
        }
        if($baseCurrency == $valuta) {
            return 100; // No conversion needed for base currency
        }

        $qtxt = "SELECT box2 FROM grupper WHERE art = 'VK' AND box1 = '$valuta'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return (float)$r['box2'];
        }

        return false;
    }

    /**
     * Get next order number
     * 
     * @return int Next order number
     */
    private static function getNextOrderNumber()
    {
        $qtxt = "SELECT MAX(ordrenr) as max_ordrenr FROM ordrer";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            return (int)$r['max_ordrenr'] + 1;
        }

        return 1;
    }

    /**
     * Get current Saldi user from header
     * 
     * @return string Saldi user
     */
    private static function getSaldiUser()
    {
        $headers = getallheaders();
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        return isset($headers['x-saldiuser']) ? $headers['x-saldiuser'] : '';
    }
}