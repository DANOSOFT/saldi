<?php
// 20261105 CL/LH Needs the Debitorordre right (debitor.ordre), as debitor/ordre.php does; without it the request is refused with 403.
//                It reads and adds lines only on its own module's orders ($orderArts); another module's order or line is "not found".
//                A number field that is not a number (positionNumber "1', ...") is a 400 before anything is stored.
require_once '../../../../models/orderlines/OrderLineModel.php';
require_once '../../../../services/OrderLineService.php';
require_once '../../../../core/BaseEndpoint.php';
require_once '../../../../core/logging.php';

class OrderLineEndpoint extends BaseEndpoint
{
    protected $permissionKey = 'debitor.ordre';

    /**
     * Order types (ordrer.art) whose lines this endpoint serves: the Debitorordre side's sales orders (DO), credit notes
     * (DK) and POS orders (PO).
     *
     * @var array<string>
     */
    protected $orderArts = array('DO', 'DK', 'PO');

    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        $orderId = $_GET['order_id'] ?? null;
        
        if ($id) {
            // Get specific orderline, when its order is one of this endpoint's order types
            $orderLine = new OrderLineModel($id);
            if ($orderLine->getId() && OrderLineService::orderHasArt($orderLine->getOrdreId(), $this->orderArts)) {
                $this->sendResponse(true, $orderLine->toArray());
            } else {
                $this->sendResponse(false, null, 'Order line not found', 404);
            }
        } elseif ($orderId) {
            // Get all orderlines for a specific order of this endpoint's order types
            if (!OrderLineService::orderHasArt($orderId, $this->orderArts)) {
                $this->sendResponse(false, null, 'Order not found', 404);
            }
            $orderLines = OrderLineModel::getByOrderId($orderId);
            $items = [];
            foreach ($orderLines as $line) {
                $items[] = $line->toArray();
            }
            $this->sendResponse(true, $items);
        } else {
            $this->sendResponse(false, null, 'Order ID or line ID is required', 400);
        }
    }

    protected function handlePost($data)
    {
        // Outside the try below, so a malformed number answers 400 (ApiException), not 500
        $this->validateNumericFields($data, OrderLineService::DECIMAL_FIELDS, OrderLineService::INTEGER_FIELDS);
        try {
            $service = new OrderLineService();
            $result = $service->createOrderLine($data, $this->orderArts);
            
            if ($result['success']) {
                $this->sendResponse(true, $result['data'], $result['message'], 201);
            } else {
                // Use the status_code from the service response, default to 500 if not set
                $statusCode = isset($result['status_code']) ? $result['status_code'] : 500;
                $this->sendResponse(false, null, $result['message'], $statusCode);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    protected function handlePut($data)
    {
        // There is no PUT method for order lines
        $this->sendResponse(false, null, 'PUT method is not supported for order lines', 405);
        return;
    }

    protected function handleDelete($data)
    {
        // There is no DELETE method for order lines
        $this->sendResponse(false, null, 'DELETE method is not supported for order lines', 405);
        return;
    }
}

$endpoint = new OrderLineEndpoint();
$endpoint->handleRequestMethod();