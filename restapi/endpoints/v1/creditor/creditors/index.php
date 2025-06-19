<?php

require_once '../../../../models/customers/CustomerModel.php';
require_once '../../../../services/CustomerService.php';
require_once '../../../../core/BaseEndpoint.php';

class CreditorEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $customer = new CustomerModel($id);
            if ($customer->getId()) {
                $this->sendResponse(true, $customer->toArray());
            } else {
                $this->sendResponse(false, null, 'Customer not found', 404);
            }
        } else {
            // Get all customers
            $customers = CustomerModel::getAllItems('K');
            $items = [];
            foreach ($customers as $customer) {
                $items[] = $customer->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }

    protected function handlePost($data)
    {
        $data->art = 'K'; // Set art to 'K' for Kreditor
        $result = CustomerService::createCustomer($data);
        
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Customer created successfully', 201);
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }

    protected function handlePut($data)
    {
        $result = CustomerService::updateCustomer($data);
        
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Customer updated successfully');
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }

    protected function handleDelete($data)
    {
        if (!isset($data->id)) {
            $this->sendResponse(false, null, 'Customer ID is required for deletion', 400);
            return;
        }

        $customer = new CustomerModel($data->id);
        if (!$customer->getId()) {
            $this->sendResponse(false, null, 'Customer not found', 404);
            return;
        }

        if ($customer->delete()) {
            $this->sendResponse(true, null, 'Customer deleted successfully');
        } else {
            $this->sendResponse(false, null, 'Failed to delete customer', 500);
        }
    }
}

$endpoint = new CreditorEndpoint();
$endpoint->handleRequestMethod();