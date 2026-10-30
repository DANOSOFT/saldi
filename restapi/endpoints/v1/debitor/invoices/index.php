<?php
/**
 * Invoices Endpoints
 * 
 * GET /invoices - Liste over fakturaer med filtering og pagination
 * GET /invoices/{id} - Detaljer for specifik faktura
 * POST /invoices - Opret ny fakturakladde
 * PUT /invoices/{id} - Opdater fakturakladde
 * POST /invoices/{id}/send - Trigger afsendelse (email)
 * GET /invoices/{id}/pdf - Hent PDF
 */

require_once __DIR__ . '/../../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../core/JWT.php';
require_once __DIR__ . '/../../../../core/JWTAuth.php';
require_once __DIR__ . '/../../../../models/orders/OrderModel.php';
require_once __DIR__ . '/../../../../models/orderlines/OrderLineModel.php';
require_once __DIR__ . '/../../../../services/OrderService.php';
require_once __DIR__ . '/../../../../core/logging.php';

include_once __DIR__ . '/../../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../../includes/connect.php';
include_once __DIR__ . '/../../../../../includes/std_func.php';
include_once __DIR__ . '/../../../../../includes/ordrefunc.php';
require_once __DIR__ . '/../../../../../includes/documents/renderDocument.php';

class InvoicesEndpoint extends BaseEndpoint
{
    protected $userId;
    protected $username;
    protected $db;
    
    public function __construct()
    {
        parent::__construct();
    }
    

    protected function handleGet($id = null)
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        // Check if requesting PDF
        if ($id && (isset($_GET['pdf']) || (isset($pathParts[count($pathParts)-1]) && $pathParts[count($pathParts)-1] === 'pdf'))) {
            $this->getPdf($id ?: (int)$pathParts[count($pathParts)-2]);
            return;
        }
        
        if ($id) {
            // Get single invoice with order lines
            $order = new OrderModel($id, 'DO');
            if ($order->getId() && $order->getStatus() >= 2) {
                $data = $order->toArray();
                
                // Get order lines
                $orderLines = OrderLineModel::getByOrderId($id);
                $lines = [];
                foreach ($orderLines as $line) {
                    $lines[] = $line->toArray();
                }
                $data['lines'] = $lines;
                
                $this->sendResponse(true, $data);
            } else {
                $this->sendResponse(false, null, 'Invoice not found or is not an invoice', 404);
            }
        } else {
            // Get list of invoices with filtering
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            // Build query
            $qtxt = "SELECT id FROM ordrer WHERE art = 'DO'";
            
            // Filter by customer (konto_id or kontonr)
            if (isset($_GET['customer'])) {
                $customerParam = db_escape_string($_GET['customer']);
                if (is_numeric($customerParam)) {
                    $customerId = (int)$customerParam;
                    $qtxt .= " AND (konto_id = $customerId OR kontonr = '$customerParam')";
                } else {
                    $qtxt .= " AND kontonr = '$customerParam'";
                }
            }
            
            // Filter by status
            if ($status === 'draft') {
                $qtxt .= " AND status = 1";
            } elseif ($status === 'sent') {
                $qtxt .= " AND status >= 2 AND status < 3";
            } elseif ($status === 'overdue') {
                // Overdue: status >= 2 and payment date has passed
                $today = date('Y-m-d');
                $qtxt .= " AND status >= 2 AND fakturadate < '$today' AND betalt != '1'";
            } else {
                // Default: all invoices (status >= 2)
                $qtxt .= " AND status >= 2";
            }
            
            $qtxt .= " ORDER BY fakturadate DESC, id DESC LIMIT $limit OFFSET $offset";
            

            
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            $invoices = [];
            
            while ($r = db_fetch_array($q)) {
                $order = new OrderModel($r['id'], 'DO');
                if ($order->getId()) {
                    $invoices[] = $order->toArray();
                }
            }
            
            $this->sendResponse(true, $invoices);
        }
    }
    
    protected function handlePost($data)
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        // Check if this is a send request
        if (isset($pathParts[count($pathParts)-1]) && $pathParts[count($pathParts)-1] === 'send') {
            $invoiceId = (int)$pathParts[count($pathParts)-2];
            $this->sendInvoice($invoiceId);
            return;
        }
        
        // Create new invoice draft
        if (!isset($data->accountId) && !isset($data->companyName)) {
            $this->sendResponse(false, null, 'Either accountId or companyName is required', 400);
            return;
        }
        
        // Set art to DO for debitor orders (invoices)
        $data->art = 'DO';
        $data->status = 1; // Draft status
        
        $result = OrderService::createOrder($data);
        
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Invoice draft created successfully', 201);
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }
    
    protected function handlePut($data)
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data->id) ? (int)$data->id : null);
        
        if (!$id) {
            $this->sendResponse(false, null, 'Invoice ID is required', 400);
            return;
        }
        
        $order = new OrderModel($id, 'DO');
        if (!$order->getId()) {
            $this->sendResponse(false, null, 'Invoice not found', 404);
            return;
        }
        
        // Update order
        $data->id = $id;
        $data->art = 'DO';
        
        // Update order using OrderModel
        $order = new OrderModel($id, 'DO');
        if (!$order->getId()) {
            $this->sendResponse(false, null, 'Invoice not found', 404);
            return;
        }
        
        // Update order fields from data
        // TODO: Implement proper update logic
        $this->sendResponse(false, null, 'Update functionality not yet fully implemented', 501);
    }
    
    private function sendInvoice($id)
    {
        $order = new OrderModel($id, 'DO');
        
        if (!$order->getId() || $order->getStatus() < 2) {
            $this->sendResponse(false, null, 'Invoice not found or not ready to send', 404);
            return;
        }
        
        $svar = bogfor_nu($id, 'webservice');
        if ($svar != "OK") {
            $this->sendResponse(false, null, $svar, 400);
            return;
        }
        
        write_log("Invoice $id sent by {$this->username}", $this->db, 'INFO');
        
        $this->sendResponse(true, ['invoiceId' => $id, 'sent' => true], 'Invoice sent successfully');
    }
    
    /**
     * Streams the PDF of a posted invoice of the caller's account, rendered by render_document()
     * (the same headless path as the Shopify document link). A missing, unposted or other-art id
     * gives 404 and no document; a render that fails (no layout, no pdftk, ...) gives 500.
     *
     * @param int|string $id ordrer.id
     * @return void
     */
    private function getPdf($id)
    {
        global $db, $db_id;

        $id = (int)$id;
        $order = new OrderModel($id, 'DO');
        if (!$id || !$order->getId() || $order->getStatus() < 3) {
            $this->sendResponse(false, null, 'Invoice not found or not posted', 404);
            return;
        }
        
        // The print code finds temp/<db>/ and logolib/<db_id>/ through the legacy globals. db_id is the
        // account the request is for, as JWTAuth::getTenantDatabase() resolves it: two accounts can share a database.
        $db = $this->db;
        $payload = JWTAuth::validateToken();
        $db_id = (int)($payload['tenant_id'] ?? 0);
        if (!$db_id) {
            $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
            $db_id = (int)($headers['x-tenant-id'] ?? 0);
        }
        try {
            $document = render_document($id, 4);
        } catch (DocumentRenderError $e) {
            write_log("Invoice $id PDF: " . $e->getMessage(), $this->db, 'WARNING');
            if ($e->getCode() === DocumentRenderError::NO_DOCUMENT) {
                $this->sendResponse(false, null, 'Invoice not found or not posted', 404);
            } else {
                $this->sendResponse(false, null, 'The invoice PDF could not be created', 500);
            }
            return;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $document['filename'] . '"');
        header('Content-Length: ' . filesize($document['path']));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($document['path']);
    }
}

$endpoint = new InvoicesEndpoint();
$endpoint->handleRequestMethod();

