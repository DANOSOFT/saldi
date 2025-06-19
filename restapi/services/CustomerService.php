<?php

require_once __DIR__ . '/../models/customers/CustomerModel.php';

class CustomerService
{
    /**
     * Create a new customer
     * 
     * @param object $data Customer data
     * @return array Result with success status and data/message
     */
    public static function createCustomer($data, $art)
    {
        try {
            // Validate required fields
            $requiredFields = ['firmanavn', 'tlf', 'email'];
            foreach ($requiredFields as $field) {
                if (!isset($data->$field) || empty(trim($data->$field))) {
                    return [
                        'success' => false,
                        'message' => "Missing required field: $field"
                    ];
                }
            }

            // Check for duplicate email
            if (CustomerModel::emailExists(trim($data->email))) {
                return [
                    'success' => false,
                    'message' => 'Email address is already in use by another customer'
                ];
            }

            // Check for duplicate phone number
            if (CustomerModel::phoneExists(trim($data->tlf))) {
                return [
                    'success' => false,
                    'message' => 'Phone number is already in use by another customer'
                ];
            }

            // Create new customer
            $customer = new CustomerModel();
            
            // Set required fields
            $customer->setFirmanavn(trim($data->firmanavn));
            $customer->setTlf(trim($data->tlf));
            $customer->setEmail(trim($data->email));
            $customer->setArt($art); // Set customer type (e.g., 'D' for debitor)
            // Set optional fields if provided
            if (isset($data->addr1)) $customer->setAddr1(trim($data->addr1));
            if (isset($data->addr2)) $customer->setAddr2(trim($data->addr2));
            if (isset($data->postnr)) $customer->setPostnr(trim($data->postnr));
            if (isset($data->bynavn)) $customer->setBynavn(trim($data->bynavn));
            if (isset($data->cvrnr)) $customer->setCvrnr(trim($data->cvrnr));
            if (isset($data->land)) $customer->setLand(trim($data->land));
            if (isset($data->bank_navn)) $customer->setBankNavn(trim($data->bank_navn));
            if (isset($data->bank_reg)) $customer->setBankReg(trim($data->bank_reg));
            if (isset($data->bank_konto)) $customer->setBankKonto(trim($data->bank_konto));
            if (isset($data->bank_fi)) $customer->setBankFi(trim($data->bank_fi));
            if (isset($data->notes)) $customer->setNotes(trim($data->notes));
            if (isset($data->betalingsbet)) $customer->setBetalingsbet(trim($data->betalingsbet));
            if (isset($data->betalingsdage)) $customer->setBetalingsdage((int)$data->betalingsdage);
            if (isset($data->ean)) $customer->setEan(trim($data->ean));
            if (isset($data->fornavn)) $customer->setFornavn(trim($data->fornavn));
            if (isset($data->efternavn)) $customer->setEfternavn(trim($data->efternavn));
            if (isset($data->kontakt)) $customer->setKontakt(trim($data->kontakt));
            if (isset($data->gruppe)) $customer->setGruppe((int)$data->gruppe);

            // Set delivery address fields if provided
            if (isset($data->lev_firmanavn)) $customer->setLevFirmanavn(trim($data->lev_firmanavn));
            if (isset($data->lev_addr1)) $customer->setLevAddr1(trim($data->lev_addr1));
            if (isset($data->lev_addr2)) $customer->setLevAddr2(trim($data->lev_addr2));
            if (isset($data->lev_postnr)) $customer->setLevPostnr(trim($data->lev_postnr));
            if (isset($data->lev_bynavn)) $customer->setLevBynavn(trim($data->lev_bynavn));
            if (isset($data->lev_tlf)) $customer->setLevTlf(trim($data->lev_tlf));
            if (isset($data->lev_email)) $customer->setLevEmail(trim($data->lev_email));
            if (isset($data->lev_land)) $customer->setLevLand(trim($data->lev_land));

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
     * @param object $data Customer data with ID
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

            // Load existing customer with art parameter
            $customer = new CustomerModel($data->id, $art);
            if (!$customer->getId()) {
                return [
                    'success' => false,
                    'message' => 'Customer not found'
                ];
            }

            // Check for duplicate email (excluding current customer)
            if (isset($data->email) && CustomerModel::emailExists(trim($data->email), $data->id)) {
                return [
                    'success' => false,
                    'message' => 'Email address is already in use by another customer'
                ];
            }

            // Check for duplicate phone number (excluding current customer)
            if (isset($data->tlf) && CustomerModel::phoneExists(trim($data->tlf), $data->id)) {
                return [
                    'success' => false,
                    'message' => 'Phone number is already in use by another customer'
                ];
            }

            // Update fields if provided
            if (isset($data->firmanavn)) $customer->setFirmanavn(trim($data->firmanavn));
            if (isset($data->tlf)) $customer->setTlf(trim($data->tlf));
            if (isset($data->email)) $customer->setEmail(trim($data->email));
            if (isset($data->addr1)) $customer->setAddr1(trim($data->addr1));
            if (isset($data->addr2)) $customer->setAddr2(trim($data->addr2));
            if (isset($data->postnr)) $customer->setPostnr(trim($data->postnr));
            if (isset($data->bynavn)) $customer->setBynavn(trim($data->bynavn));
            if (isset($data->notes)) $customer->setNotes(trim($data->notes));
            if (isset($data->cvrnr)) $customer->setCvrnr(trim($data->cvrnr));
            if (isset($data->land)) $customer->setLand(trim($data->land));
            if (isset($data->fornavn)) $customer->setFornavn(trim($data->fornavn));
            if (isset($data->efternavn)) $customer->setEfternavn(trim($data->efternavn));

            // Save the customer (it will update since ID is already set)
            if ($customer->save()) {
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