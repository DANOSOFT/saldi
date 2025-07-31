<?php

require_once __DIR__ . '/../models/orders/OrderModel.php';

class OrderService
{
    /**
     * Map English property names to Danish database column names
     */
    private static function mapApiToDanish($data)
    {
        $mapping = [
            // Basic order info
            'accountId' => 'konto_id',
            'companyName' => 'firmanavn',
            'phone' => 'telefon',
            'email' => 'email', // same
            'vatRate' => 'momssats',
            'orderNumber' => 'ordrenr',
            'orderDate' => 'ordredate',
            'invoiceDate' => 'fakturadate',
            'notes' => 'notes', // same
            'paid' => 'betalt',
            'sum' => 'sum', // same
            'costPrice' => 'kostpris',
            'vat' => 'moms',
            'currency' => 'valuta',
            'currencyRate' => 'valutakurs',
            'paymentTerms' => 'betalingsbet',
            'paymentDays' => 'betalingsdage',
            'accountNumber' => 'kontonr',
            'reference' => 'ref',
            'status' => 'status', // same
            'type' => 'art',
            
            // Address fields
            'address1' => 'addr1',
            'address2' => 'addr2',
            'postalCode' => 'postnr',
            'city' => 'bynavn',
            'country' => 'land',
            
            // Delivery address
            'deliveryName' => 'lev_navn',
            'deliveryAddress1' => 'lev_addr1',
            'deliveryAddress2' => 'lev_addr2',
            'deliveryPostalCode' => 'lev_postnr',
            'deliveryCity' => 'lev_bynavn',
            'deliveryCountry' => 'lev_land',
            
            // Other fields
            'ean' => 'ean', // same
            'cvrNo' => 'cvrnr',
            'customerGroup' => 'kundegruppe'
        ];
        
        $mappedData = new stdClass();
        
        // Map English properties to Danish, but keep Danish properties as-is for backward compatibility
        foreach ($data as $key => $value) {
            if (isset($mapping[$key])) {
                // Use Danish property name
                $danishKey = $mapping[$key];
                $mappedData->$danishKey = $value;
            } else {
                // Keep original property name (for backward compatibility with Danish names)
                $mappedData->$key = $value;
            }
        }
        
        return $mappedData;
    }

    /**
     * Create a new order with validation and business logic
     * 
     * @param object $data Order data from request (can use English or Danish property names)
     * @return array Result with success status and data/message
     */
    public static function createOrder($data)
    {
        try {
            // Map English property names to Danish
            $mappedData = self::mapApiToDanish($data);
            
            // Validate required fields (check both English and Danish names)
            if (!isset($mappedData->konto_id) && !isset($data->accountId)) {
                $requiredFields = [
                    ['firmanavn', 'companyName'],
                    ['telefon', 'phone'], 
                    ['email', 'email'],
                    ['momssats', 'vatRate']
                ];
                
                foreach ($requiredFields as $fieldOptions) {
                    $found = false;
                    foreach ($fieldOptions as $field) {
                        if ((isset($data->$field) && !empty(trim($data->$field))) || 
                            (isset($mappedData->$field) && !empty(trim($mappedData->$field)))) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $englishField = end($fieldOptions);
                        return ['success' => false, 'message' => "Required field missing: $englishField"];
                    }
                }
            } else {
                // If konto_id is provided, we still need vatRate
                if (!isset($mappedData->momssats) && !isset($data->vatRate)) {
                    return ['success' => false, 'message' => "Required field missing: vatRate"];
                }
            }

            $order = new OrderModel();
            
            // Set required fields using mapped data
            $order->setMomssats($mappedData->momssats ?? 0);
            $order->setArt($mappedData->art ?? 'DO');
            
            // Check if user exists by phone number and get/create konto_id and kontonr
            $debtorInfo = self::getOrCreateDebtor($mappedData);
            if ($debtorInfo === false) {
                return ['success' => false, 'message' => 'Failed to create or find debtor'];
            }
            
            $order->setKontoId($debtorInfo['id']);
            $order->setKontonr($debtorInfo['kontonr']);
            $order->setBynavn($debtorInfo['bynavn']);
            $order->setAddr1($debtorInfo['addr1']);
            $order->setPostnr($debtorInfo['postnr']);
            $order->setTelefon($debtorInfo['tlf']);
            $order->setEmail($debtorInfo['email']);
            $order->setFirmanavn($debtorInfo['firmanavn']);

            // Use existing user payment terms or set defaults
            $order->setBetalingsbet($mappedData->betalingsbet ?? $debtorInfo['betalingsbet']);
            $order->setBetalingsdage($mappedData->betalingsdage ?? $debtorInfo['betalingsdage']);

            // Set optional fields using mapped data
            $order->setSum($mappedData->sum ?? 0);
            $order->setKostpris($mappedData->kostpris ?? 0);
            $order->setMoms($mappedData->moms ?? 0);
            
            // Handle currency
            $valuta = self::getValuta($mappedData);
            $order->setValuta($valuta);
            
            // Get exchange rate
            $valutakurs = self::getValutakurs($valuta);
            if ($valutakurs === false) {
                return ['success' => false, 'message' => "Currency exchange rate not found for: $valuta"];
            }
            $order->setValutakurs($valutakurs);

            // Set address fields
            if (isset($mappedData->addr1)) $order->setAddr1($mappedData->addr1);
            if (isset($mappedData->addr2)) $order->setAddr2($mappedData->addr2);
            if (isset($mappedData->postnr)) $order->setPostnr($mappedData->postnr);
            if (isset($mappedData->bynavn)) $order->setBynavn($mappedData->bynavn);
            if (isset($mappedData->land)) $order->setLand($mappedData->land);

            // Set delivery fields
            if (isset($mappedData->lev_navn)) $order->setLevNavn($mappedData->lev_navn);
            if (isset($mappedData->lev_addr1)) $order->setLevAddr1($mappedData->lev_addr1);
            if (isset($mappedData->lev_addr2)) $order->setLevAddr2($mappedData->lev_addr2);
            if (isset($mappedData->lev_postnr)) $order->setLevPostnr($mappedData->lev_postnr);
            if (isset($mappedData->lev_bynavn)) $order->setLevBynavn($mappedData->lev_bynavn);
            if (isset($mappedData->lev_land)) $order->setLevLand($mappedData->lev_land);

            // Set other fields
            if (isset($mappedData->ean)) $order->setEan($mappedData->ean);
            if (isset($mappedData->cvrnr)) $order->setCvrnr($mappedData->cvrnr);
            
            // Handle dates
            $order->setOrdredate($mappedData->ordredate ?? date('Y-m-d H:i:s'));
            $order->setFakturadate($mappedData->fakturadate ?? date('Y-m-d H:i:s'));
            
            if (isset($mappedData->notes)) $order->setNotes($mappedData->notes);

            // Handle betalt field
            $betalt = (isset($mappedData->betalt) && $mappedData->betalt) ? 'on' : '';
            $order->setBetalt($betalt);

            // Set system fields
            $order->setRef(self::getSaldiUser());
            $order->setStatus($mappedData->status ?? 0);
            $order->setOrdrenr(self::getNextOrderNumber());

            // Save the order
            if ($order->save()) {
                return ['success' => true, 'data' => $order->toArray()];
            } else {
                return ['success' => false, 'message' => 'Failed to save order'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error creating order: ' . $e->getMessage()];
        }
    }

    /**
     * Get existing debtor or create new one if not found
     * According to ordrer.txt: check tlf in adresser, if exists use id and kontonr, if not create new
     * 
     * @param object $data Order data
     * @return array|false Debtor info (id, kontonr, betalingsbet, betalingsdage) or false on error
     */
    private static function getOrCreateDebtor($mappedData)
    {
        // First, check if user exists by phone number
        $existingDebtor = null;
        if(isset($mappedData->telefon)) {
            $existingDebtor = self::getUserByPhone($mappedData->telefon);
        }

        if ($existingDebtor) {
            // User exists, return their info
            return [
                'id' => $existingDebtor['id'],
                'kontonr' => $existingDebtor['kontonr'],
                'betalingsbet' => $existingDebtor['betalingsbet'] ?: 'netto',
                'betalingsdage' => $existingDebtor['betalingsdage'] ?: 8,
                'addr1' => $existingDebtor['addr1'] ?: '',
                'bynavn' => $existingDebtor['bynavn'] ?: '',
                'tlf' => $existingDebtor['tlf'] ?: '',
                'email' => $existingDebtor['email'] ?: '',
                'postnr' => $existingDebtor['postnr'] ?: '',
                'firmanavn' => $existingDebtor['firmanavn'] ?: ''
            ];
        } else {
            $existingDebtor = self::getUserByKontoId($mappedData->konto_id ?? null);

            if ($existingDebtor) {
                // User exists by konto_id, return their info
                return [
                    'id' => $existingDebtor['id'],
                    'kontonr' => $existingDebtor['kontonr'],
                    'betalingsbet' => $existingDebtor['betalingsbet'] ?: 'netto',
                    'betalingsdage' => $existingDebtor['betalingsdage'] ?: 8,
                    'addr1' => $existingDebtor['addr1'] ?: '',
                    'bynavn' => $existingDebtor['bynavn'] ?: '',
                    'tlf' => $existingDebtor['tlf'] ?: '',
                    'email' => $existingDebtor['email'] ?: '',
                    'postnr' => $existingDebtor['postnr'] ?: '',
                    'firmanavn' => $existingDebtor['firmanavn'] ?: ''
                ];
            } else {
                // User doesn't exist, create new debtor
                return self::createNewDebtor($mappedData);
            }
        }
    }


    /**
     * Get user by konto_id from adresser table
     * 
     * @param int $konto_id Konto ID
     * @return array|null User data or null if not found
     */
    private static function getUserByKontoId($konto_id)
    {
        if (!$konto_id) return null;
        
        $qtxt = "SELECT id, kontonr, betalingsbet, betalingsdage, addr1, bynavn, tlf, email, postnr, firmanavn FROM adresser WHERE id = '$konto_id'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($r = db_fetch_array($q)) {
            return $r;
        }
        return null;
    }

    /**
     * Get user by phone number from adresser table
     * 
     * @param string $phone Phone number
     * @return array|null User data or null if not found
     */
    private static function getUserByPhone($phone)
    {
        $qtxt = "SELECT id, kontonr, betalingsbet, betalingsdage, addr1, bynavn, tlf, email, postnr, firmanavn FROM adresser WHERE tlf = '$phone'";
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
    private static function createNewDebtor($mappedData)
    {
        $nextKontonr = self::getNextKontonr();
        
        // Prepare address data with defaults
        $addr1 = $mappedData->addr1 ?? '';
        $addr2 = $mappedData->addr2 ?? '';
        $postnr = $mappedData->postnr ?? '';
        $bynavn = $mappedData->bynavn ?? '';
        $land = $mappedData->land ?? '';
        $ean = $mappedData->ean ?? '';
        $cvrnr = $mappedData->cvrnr ?? '';

        // Handle kundegruppe - default to 1 if not provided
        $kundegruppe = $mappedData->kundegruppe ?? 1;

        // Insert new debitor
        $qtxt = "INSERT INTO adresser (
            firmanavn, tlf, email, addr1, addr2, postnr, bynavn, land, 
            ean, cvrnr, kontonr, betalingsbet, betalingsdage, art, gruppe
        ) VALUES (
            '{$mappedData->firmanavn}', '{$mappedData->telefon}', '{$mappedData->email}', '$addr1', '$addr2', 
            '$postnr', '$bynavn', '$land', '$ean', '$cvrnr', '$nextKontonr', 'netto', 8, 'D', '$kundegruppe'
        )";

        $result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        
        if (explode("\t", $result)[0] == "0") {
            // Success, get the new ID
            $newId = self::getLastInsertId($mappedData->telefon);
            if ($newId) {
                return [
                    'id' => $newId,
                    'kontonr' => $nextKontonr,
                    'betalingsbet' => 'netto',
                    'betalingsdage' => 8,
                    'addr1' => $addr1,
                    'bynavn' => $bynavn,
                    'tlf' => $mappedData->telefon,
                    'email' => $mappedData->email,
                    'postnr' => $postnr,
                    'firmanavn' => $mappedData->firmanavn
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
    private static function getLastInsertId($tlf)
    {
        $qtxt = "SELECT id FROM adresser WHERE tlf = '$tlf' LIMIT 1";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($q) {
            $r = db_fetch_array($q);
            return (int)$r['id'];
        }
        
        return false;
    }

    /**
     * Get currency from input or settings
     * 
     * @param object $data Input data
     * @return string Currency code
     */
    private static function getValuta($mappedData)
    {
        if (isset($mappedData->valuta) && !empty($mappedData->valuta)) {
            return $mappedData->valuta;
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
        } else {
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