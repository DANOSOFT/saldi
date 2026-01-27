<?php

require_once __DIR__ . '/../models/customers/CustomerModel.php';

class CustomerService
{
    /**
     * Map English property names to Danish database column names
     */
    private static function mapApiToDanish($data)
    {
        $mapping = [
            // Basic info
            'companyName' => 'firmanavn',
            'phone' => 'tlf',
            'email' => 'email', // same
            'firstName' => 'fornavn',
            'lastName' => 'efternavn',
            'contact' => 'kontakt',
            'notes' => 'notes', // same
            'group' => 'gruppe',
            'accountNumber' => 'kontonr',
            'ean' => 'ean', // same
            'vatNumber' => 'cvrnr',
            'country' => 'land',
            
            // Address
            'address1' => 'addr1',
            'address2' => 'addr2',
            'postalCode' => 'postnr',
            'city' => 'bynavn',
            
            // Bank info
            'bankName' => 'bank_navn',
            'bankReg' => 'bank_reg',
            'bankAccount' => 'bank_konto',
            'bankFi' => 'bank_fi',
            
            // Payment terms
            'paymentTerms' => 'betalingsbet',
            'paymentDays' => 'betalingsdage',
            
            // Delivery address
            'deliveryCompanyName' => 'lev_firmanavn',
            'deliveryAddress1' => 'lev_addr1',
            'deliveryAddress2' => 'lev_addr2',
            'deliveryPostalCode' => 'lev_postnr',
            'deliveryCity' => 'lev_bynavn',
            'deliveryPhone' => 'lev_tlf',
            'deliveryEmail' => 'lev_email',
            'deliveryCountry' => 'lev_land'
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
     * Create a new customer
     * 
     * @param object $data Customer data (can use English or Danish property names)
     * @return array Result with success status and data/message
     */
    public static function createCustomer($data, $art)
    {
        try {
            // Map English property names to Danish
            $mappedData = self::mapApiToDanish($data);
            
            // Validate required fields (check both English and Danish names)
            $requiredFields = [
                ['firmanavn', 'companyName'],  // Danish, English
                ['tlf', 'phone'],
                ['email', 'email']
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
                    $englishField = end($fieldOptions); // Get the English name for error message
                    return [
                        'success' => false,
                        'message' => "Missing required field: $englishField"
                    ];
                }
            }

            // Check for duplicate email
            $email = $mappedData->email ?? $data->email ?? '';
            if (!empty($email) && CustomerModel::emailExists(trim($email))) {
                return [
                    'success' => false,
                    'message' => 'Email address is already in use by another customer'
                ];
            }

            // Check for duplicate phone number
            $phone = $mappedData->tlf ?? $data->phone ?? '';
            if (!empty($phone) && CustomerModel::phoneExists(trim($phone))) {
                return [
                    'success' => false,
                    'message' => 'Phone number is already in use by another customer'
                ];
            }

            // Create new customer
            $customer = new CustomerModel();
            
            // Set required fields
            $customer->setFirmanavn(trim($mappedData->firmanavn ?? ''));
            $customer->setTlf(trim($mappedData->tlf ?? ''));
            $customer->setEmail(trim($mappedData->email ?? ''));
            $customer->setArt($art);
            
            // Set optional fields if provided
            if (isset($mappedData->addr1)) $customer->setAddr1(trim($mappedData->addr1));
            if (isset($mappedData->addr2)) $customer->setAddr2(trim($mappedData->addr2));
            if (isset($mappedData->postnr)) $customer->setPostnr(trim($mappedData->postnr));
            if (isset($mappedData->bynavn)) $customer->setBynavn(trim($mappedData->bynavn));
            if (isset($mappedData->cvrnr)) $customer->setCvrnr(trim($mappedData->cvrnr));
            if (isset($mappedData->land)) $customer->setLand(trim($mappedData->land));
            if (isset($mappedData->bank_navn)) $customer->setBankNavn(trim($mappedData->bank_navn));
            if (isset($mappedData->bank_reg)) $customer->setBankReg(trim($mappedData->bank_reg));
            if (isset($mappedData->bank_konto)) $customer->setBankKonto(trim($mappedData->bank_konto));
            if (isset($mappedData->bank_fi)) $customer->setBankFi(trim($mappedData->bank_fi));
            if (isset($mappedData->notes)) $customer->setNotes(trim($mappedData->notes));
            if (isset($mappedData->betalingsbet)) $customer->setBetalingsbet(trim($mappedData->betalingsbet));
            if (isset($mappedData->betalingsdage)) $customer->setBetalingsdage((int)$mappedData->betalingsdage);
            if (isset($mappedData->ean)) $customer->setEan(trim($mappedData->ean));
            if (isset($mappedData->fornavn)) $customer->setFornavn(trim($mappedData->fornavn));
            if (isset($mappedData->efternavn)) $customer->setEfternavn(trim($mappedData->efternavn));
            if (isset($mappedData->kontakt)) $customer->setKontakt(trim($mappedData->kontakt));
            if (isset($mappedData->gruppe)) $customer->setGruppe((int)$mappedData->gruppe);

            // Set delivery address fields if provided
            if (isset($mappedData->lev_firmanavn)) $customer->setLevFirmanavn(trim($mappedData->lev_firmanavn));
            if (isset($mappedData->lev_addr1)) $customer->setLevAddr1(trim($mappedData->lev_addr1));
            if (isset($mappedData->lev_addr2)) $customer->setLevAddr2(trim($mappedData->lev_addr2));
            if (isset($mappedData->lev_postnr)) $customer->setLevPostnr(trim($mappedData->lev_postnr));
            if (isset($mappedData->lev_bynavn)) $customer->setLevBynavn(trim($mappedData->lev_bynavn));
            if (isset($mappedData->lev_tlf)) $customer->setLevTlf(trim($mappedData->lev_tlf));
            if (isset($mappedData->lev_email)) $customer->setLevEmail(trim($mappedData->lev_email));
            if (isset($mappedData->lev_land)) $customer->setLevLand(trim($mappedData->lev_land));

            // Save customer
            if ($customer->save()) {
                return [
                    'success' => true,
                    'data' => $customer->toArray()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to save customer to database'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating customer: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing customer
     * 
     * @param object $data Customer data with ID (can use English or Danish property names)
     * @return array Result with success status and data/message
     */
    public static function updateCustomer($data, $art)
    {
        try {
            if (!isset($data->id)) {
                return [
                    'success' => false,
                    'message' => 'Customer ID is required for update'
                ];
            }

            // Map English property names to Danish
            $mappedData = self::mapApiToDanish($data);

            // Load existing customer with art parameter
            $customer = new CustomerModel($data->id, $art);
            if (!$customer->getId()) {
                return [
                    'success' => false,
                    'message' => 'Customer not found'
                ];
            }

            // IMPORTANT: Set the art property on the customer object
            $customer->setArt($art);

            // Check for duplicate email (excluding current customer)
            $email = $mappedData->email ?? $data->email ?? null;
            if ($email && CustomerModel::emailExists(trim($email), $data->id)) {
                return [
                    'success' => false,
                    'message' => 'Email address is already in use by another customer'
                ];
            }

            // Check for duplicate phone number (excluding current customer)
            $phone = $mappedData->tlf ?? $data->phone ?? null;
            if ($phone && CustomerModel::phoneExists(trim($phone), $data->id)) {
                return [
                    'success' => false,
                    'message' => 'Phone number is already in use by another customer'
                ];
            }

            // Update fields if provided (using mapped Danish property names)
            if (isset($mappedData->firmanavn)) $customer->setFirmanavn(trim($mappedData->firmanavn));
            if (isset($mappedData->tlf)) $customer->setTlf(trim($mappedData->tlf));
            if (isset($mappedData->email)) $customer->setEmail(trim($mappedData->email));
            if (isset($mappedData->addr1)) $customer->setAddr1(trim($mappedData->addr1));
            if (isset($mappedData->addr2)) $customer->setAddr2(trim($mappedData->addr2));
            if (isset($mappedData->postnr)) $customer->setPostnr(trim($mappedData->postnr));
            if (isset($mappedData->bynavn)) $customer->setBynavn(trim($mappedData->bynavn));
            if (isset($mappedData->notes)) $customer->setNotes(trim($mappedData->notes));
            if (isset($mappedData->cvrnr)) $customer->setCvrnr(trim($mappedData->cvrnr));
            if (isset($mappedData->land)) $customer->setLand(trim($mappedData->land));
            if (isset($mappedData->fornavn)) $customer->setFornavn(trim($mappedData->fornavn));
            if (isset($mappedData->efternavn)) $customer->setEfternavn(trim($mappedData->efternavn));
            if (isset($mappedData->kontakt)) $customer->setKontakt(trim($mappedData->kontakt));
            if (isset($mappedData->gruppe)) $customer->setGruppe((int)$mappedData->gruppe);
            
            // Bank fields
            if (isset($mappedData->bank_navn)) $customer->setBankNavn(trim($mappedData->bank_navn));
            if (isset($mappedData->bank_reg)) $customer->setBankReg(trim($mappedData->bank_reg));
            if (isset($mappedData->bank_konto)) $customer->setBankKonto(trim($mappedData->bank_konto));
            if (isset($mappedData->bank_fi)) $customer->setBankFi(trim($mappedData->bank_fi));
            
            // Payment fields
            if (isset($mappedData->betalingsbet)) $customer->setBetalingsbet(trim($mappedData->betalingsbet));
            if (isset($mappedData->betalingsdage)) $customer->setBetalingsdage((int)$mappedData->betalingsdage);
            
            // Delivery address fields
            if (isset($mappedData->lev_firmanavn)) $customer->setLevFirmanavn(trim($mappedData->lev_firmanavn));
            if (isset($mappedData->lev_addr1)) $customer->setLevAddr1(trim($mappedData->lev_addr1));
            if (isset($mappedData->lev_addr2)) $customer->setLevAddr2(trim($mappedData->lev_addr2));
            if (isset($mappedData->lev_postnr)) $customer->setLevPostnr(trim($mappedData->lev_postnr));
            if (isset($mappedData->lev_bynavn)) $customer->setLevBynavn(trim($mappedData->lev_bynavn));
            if (isset($mappedData->lev_tlf)) $customer->setLevTlf(trim($mappedData->lev_tlf));
            if (isset($mappedData->lev_email)) $customer->setLevEmail(trim($mappedData->lev_email));
            if (isset($mappedData->lev_land)) $customer->setLevLand(trim($mappedData->lev_land));

            // if id is in data object, set it to the customer
            $id = isset($data->id) ? (int)$data->id : null;

            // Save the customer
            if ($customer->save($id)) {
                return [
                    'success' => true,
                    'data' => $customer->toArray()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update customer'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating customer: ' . $e->getMessage()
            ];
        }
    }
}