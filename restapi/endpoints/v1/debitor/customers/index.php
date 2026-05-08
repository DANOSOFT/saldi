<?php

require_once '../../../../models/customers/CustomerModel.php';
require_once '../../../../services/CustomerService.php';
require_once '../../../../core/BaseEndpoint.php';

class CustomerEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $customer = new CustomerModel($id, 'D'); // 'D' for debitor customers
            if ($customer->getId()) {
                $this->sendResponse(true, $customer->toArray());
            } else {
                $this->sendResponse(false, null, 'Customer not found', 404);
            }
        } else {
            // Get all customers with optional search
            $search = isset($_GET['search']) ? $_GET['search'] : null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $customers = CustomerModel::getAllItems('D', $search, $limit, $offset);
            $items = [];
            foreach ($customers as $customer) {
                $items[] = $customer->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }

    protected function handlePost($data)
    {
        /* $data->art = 'D'; // Set art to 'D' for debitor customers */
        $result = CustomerService::createCustomer($data, 'D');
        
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Customer created successfully', 201);
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }

    protected function handlePut($data)
    {
        // first, try to read an ?id= from the query string
        $id = isset($_GET['id'])
            ? (int)$_GET['id']
            : (isset($data->id) ? (int)$data->id : null);

        if (!$id) {
            $this->sendResponse(false, null, 'Customer ID is required for update', 400);
            return;
        }

        // override any body‐id with the query‐string
        $data->id = $id;

        $result = CustomerService::updateCustomer($data, 'D');
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Customer updated successfully');
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }

    protected function handleDelete($data)
    {
        // same pattern for delete
        $id = isset($_GET['id'])
            ? (int)$_GET['id']
            : (isset($data->id) ? (int)$data->id : null);

        if (!$id) {
            $this->sendResponse(false, null, 'Customer ID is required for deletion', 400);
            return;
        }

        $customer = new CustomerModel($id, 'D');
        if (!$customer->getId()) {
            $this->sendResponse(false, null, 'Customer not found', 404);
            return;
        }

        if ($customer->delete("D")) {
            $this->sendResponse(true, null, 'Customer deleted successfully');
        } else {
            $this->sendResponse(false, null, 'Failed to delete customer', 500);
        }
    }
}

$endpoint = new CustomerEndpoint();
$endpoint->handleRequestMethod();