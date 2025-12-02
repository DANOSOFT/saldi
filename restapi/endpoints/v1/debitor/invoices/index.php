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

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/JWTAuth.php';
require_once __DIR__ . '/../../../models/orders/OrderModel.php';
require_once __DIR__ . '/../../../models/orderlines/OrderLineModel.php';
require_once __DIR__ . '/../../../services/OrderService.php';
require_once __DIR__ . '/../../../core/logging.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';
include_once __DIR__ . '/../../../../includes/std_func.php';
include_once __DIR__ . '/../../../../includes/ordrefunc.php';

class InvoicesEndpoint extends BaseEndpoint
{
    private $userId;
    private $username;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    protected function checkAuthorization()
    {
        $payload = JWTAuth::validateToken();
        
        if (!$payload) {
            $this->sendResponse(false, null, 'Invalid or expired token', 401);
            return false;
        }
        
        $this->userId = $payload['user_id'];
        $this->username = $payload['username'];
        
        // Get database from tenant
        $this->db = JWTAuth::getTenantDatabase();
        if (!$this->db) {
            $this->sendResponse(false, null, 'Tenant database not found. Set X-Tenant-ID header.', 400);
            return false;
        }
        
        return true;
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
            
            global $sqhost, $squser, $sqpass;
            $conn = db_connect($sqhost, $squser, $sqpass, $this->db, __FILE__ . " linje " . __LINE__);
            
            if (!$conn) {
                $this->sendResponse(false, null, 'Database connection failed', 500);
                return;
            }
            
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
    
    private function getPdf($id)
    {
        $order = new OrderModel($id, 'DO');
        
        if (!$order->getId() || $order->getStatus() < 2) {
            $this->sendResponse(false, null, 'Invoice not found or not ready', 404);
            return;
        }
        
        // TODO: Generate PDF using existing system functions
        // For now, return a placeholder response
        $this->sendResponse(false, null, 'PDF generation not yet implemented. Use existing invoice PDF generation.', 501);
    }
}

$endpoint = new InvoicesEndpoint();
$endpoint->handleRequestMethod();

