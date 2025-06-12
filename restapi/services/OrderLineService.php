<?php

require_once __DIR__ . '/../models/orderlines/OrderLineModel.php';

class OrderLineService 
{
    private $regnaar;
    private $momssats;

    public function __construct() 
    {
        global $momssats;
        $this->regnaar = $this->getCurrentFiscalYear();
        $this->momssats = $momssats ?: 25.0;
    }

    /**
     * Main function to create order line for REST API
     */
    public function createOrderLine($data, $db = 'unknown') 
    {
        try {
            // Convert object to array for easier processing
            $params = (array) $data;
            
            // Validate required fields
            if (empty($params['ordre_id'])) {
                return [
                    'success' => false, 
                    'message' => 'Missing required field: ordre_id',
                    'status_code' => 400
                ];
            }

            $orderId = $params['ordre_id'];

            // Validate order exists and status
            $orderInfo = $this->getOrderInfo($orderId);
            if (!$orderInfo) {
                return [
                    'success' => false, 
                    'message' => 'Order not found',
                    'status_code' => 400
                ];
            }

            if ($orderInfo['status'] >= 3) {
                return [
                    'success' => false, 
                    'message' => 'Cannot add lines to a posted order',
                    'status_code' => 400
                ];
            }

            // Set defaults
            $this->setDefaults($params);

            // Handle variant lookup if needed
            $variantInfo = null;
            if (!empty($params['varenr'])) {
                $variantInfo = $this->handleVariants($params['varenr']);
            }

            // Get product information
            $productInfo = null;
            if (!empty($params['vare_id']) || !empty($params['varenr'])) {
                $productInfo = $this->getProductInfo(
                    $params['vare_id'] ?? null, 
                    $params['varenr'] ?? null, 
                    $variantInfo
                );
            }

            // If no product found and no manual description, return error
            if (!$productInfo && empty($params['beskrivelse'])) {
                return [
                    'success' => false, 
                    'message' => 'Product not found and no description provided',
                    'status_code' => 400
                ];
            }

            // Calculate pricing
            $pricing = $this->calculatePricing($productInfo, $orderInfo, $params);

            // Create the order line
            $lineData = [
                'ordre_id' => $orderId,
                'productInfo' => $productInfo,
                'variantInfo' => $variantInfo,
                'pricing' => $pricing,
                'orderInfo' => $orderInfo,
                'params' => $params
            ];

            $result = $this->insertNewOrderLine($lineData);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'data' => $result['data'],
                    'message' => 'Order line created successfully'
                ];
            } else {
                return ['success' => false, 'message' => $result['message']];
            }

        } catch (Exception $e) {
            return [
                'success' => false, 
                'message' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    private function getCurrentFiscalYear()
    {
        $query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
        
        // Add error checking here
        if (!$query) {
            error_log("Failed to get fiscal year data");
            return date('Y'); // Return current year as fallback
        }
        
        $currentYear = date('Y');
        $currentMonth = date('m');
        $regnaar = null;
        
        while($row = db_fetch_array($query)){
            $box1 = $row['box1']; // Starting month
            $box2 = $row['box2']; // Starting year
            $box3 = $row['box3']; // Ending month
            $box4 = $row['box4']; // Ending year
            $kodenr = $row['kodenr'];

            if (($currentYear > $box2 || ($currentYear == $box2 && $currentMonth >= $box1)) &&
                ($currentYear < $box4 || ($currentYear == $box4 && $currentMonth <= $box3))) {
                $regnaar = $kodenr;
                break;
            }
        }
        
        return $regnaar;
    }

    private function setDefaults(&$params) 
    {
        $params['antal'] = $params['antal'] ?? 1;
        $params['procent'] = $params['procent'] ?? 100;
        $params['rabat'] = $params['rabat'] ?? 0;
        $params['momsfri'] = $params['momsfri'] ?? 0;
        $params['pris'] = $params['pris'] ?? 0;
    }

    private function getOrderInfo($orderId) 
    {
        $qtxt = "SELECT ordrer.art, ordrer.status, ordrer.valutakurs, ordrer.afd, 
                 adresser.gruppe as debitorgruppe, adresser.rabatgruppe as debitorrabatgruppe 
                 FROM adresser, ordrer 
                 WHERE ordrer.id='$orderId' AND adresser.id=ordrer.konto_id";
                 
        $result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if (!$result) {
            error_log("Get order info failed for order ID: $orderId");
            return null;
        }
        
        if ($r = db_fetch_array($result)) {
            return [
                'art' => $r['art'],
                'status' => $r['status'],
                'valutakurs' => $r['valutakurs'],
                'afd' => $r['afd'],
                'debitorgruppe' => $r['debitorgruppe'],
                'debitorrabatgruppe' => $r['debitorrabatgruppe']
            ];
        }
        return null;
    }

    private function handleVariants($varenr) 
    {
        if (!$varenr) return null;

        $varenr_up = strtoupper($varenr);
        
        $qtxt = "SELECT id, vare_id, variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'";
        if (strlen($varenr) == 12 && is_numeric($varenr)) {
            $qtxt .= " OR variant_stregkode='0$varenr'";
        }

        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return [
                'id' => $r['id'],
                'vare_id' => $r['vare_id'],
                'variant_type' => $r['variant_type']
            ];
        }
        
        return null;
    }

    private function getProductInfo($vare_id, $varenr, $variantInfo) 
    {
        if ($vare_id && $variantInfo) {
            $vare_id = $variantInfo['vare_id'];
        }

        if ($vare_id) {
            $qtxt = "SELECT * FROM varer WHERE id='$vare_id'";
        } elseif ($varenr) {
            $varenr_escaped = db_escape_string($varenr);
            $varenr_low = strtolower($varenr_escaped);
            $varenr_up = strtoupper($varenr_escaped);
            
            $qtxt = "SELECT * FROM varer WHERE 
                    lower(varenr) = '$varenr_low' OR upper(varenr) = '$varenr_up' 
                    OR lower(stregkode) = '$varenr_low' OR upper(stregkode) = '$varenr_up'";
            
            if (strlen($varenr) == 12 && is_numeric($varenr)) {
                $qtxt .= " OR stregkode='0$varenr'";
            }
        } else {
            return null;
        }

        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return [
                'id' => $r['id'],
                'varenr' => $r['varenr'],
                'enhed' => $r['enhed'],
                'beskrivelse' => $r['beskrivelse'],
                'salgspris' => $r['salgspris'],
                'kostpris' => $r['kostpris'],
                'gruppe' => $r['gruppe']
            ];
        }

        return null;
    }

    private function calculatePricing($productInfo, $orderInfo, $params) 
    {
        $pricing = [
            'price' => $params['pris'] ?: ($productInfo ? $productInfo['salgspris'] : 0),
            'cost' => $productInfo ? $productInfo['kostpris'] : 0,
            'discount' => $params['rabat'] ?: 0,
            'vat_rate' => 0,
            'vat_account' => 0
        ];

        // Calculate VAT if not VAT-free
        if (!$params['momsfri'] && $productInfo) {
            $vatInfo = $this->calculateVAT($productInfo['gruppe']);
            $pricing['vat_rate'] = $vatInfo['rate'];
            $pricing['vat_account'] = $vatInfo['account'];
        }

        // Apply currency conversion
        if ($orderInfo['valutakurs'] && $orderInfo['valutakurs'] != 100) {
            $pricing['price'] = $pricing['price'] * 100 / $orderInfo['valutakurs'];
            $pricing['cost'] = $pricing['cost'] * 100 / $orderInfo['valutakurs'];
        }

        // Calculate VAT price
        if ($params['momsfri']) {
            $pricing['vat_price'] = $pricing['price'];
        } else {
            $pricing['vat_price'] = $pricing['price'] + ($pricing['price'] * $pricing['vat_rate'] / 100);
        }

        return $pricing;
    }

    private function calculateVAT($varegruppe) 
    {
        // Query 1: Get box4 from varegruppe
        $qtxt = "SELECT box4 FROM grupper WHERE art = 'VG' AND kodenr = '$varegruppe' AND fiscal_year = '{$this->regnaar}'";
        $result1 = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if (!$result1) {
            error_log("VAT Query 1 failed: $qtxt");
            return ['account' => 0, 'rate' => $this->momssats];
        }
        
        if ($r = db_fetch_array($result1)) {
            $bogfkto = $r['box4'];
            
            if ($bogfkto) {
                // Query 2: Get moms from kontoplan
                $qtxt2 = "SELECT moms FROM kontoplan WHERE kontonr = '$bogfkto' AND regnskabsaar = '{$this->regnaar}'";
                $result2 = db_select($qtxt2, __FILE__ . " linje " . __LINE__);
                
                if (!$result2) {
                    error_log("VAT Query 2 failed: $qtxt2");
                    return ['account' => 0, 'rate' => $this->momssats];
                }
                
                if ($r2 = db_fetch_array($result2)) {
                    $momsCode = (int) substr($r2['moms'], 1);
                    if ($momsCode) {
                        // Query 3: Get VAT rate
                        $qtxt3 = "SELECT box1, box2 FROM grupper WHERE art = 'SM' AND kodenr = '$momsCode' AND fiscal_year = '{$this->regnaar}'";
                        $result3 = db_select($qtxt3, __FILE__ . " linje " . __LINE__);
                        
                        if (!$result3) {
                            error_log("VAT Query 3 failed: $qtxt3");
                            return ['account' => 0, 'rate' => $this->momssats];
                        }
                        
                        if ($r3 = db_fetch_array($result3)) {
                            return [
                                'account' => (int) $r3['box1'],
                                'rate' => (float) $r3['box2']
                            ];
                        }
                    }
                }
            }
        }

        return ['account' => 0, 'rate' => $this->momssats];
    }

    private function insertNewOrderLine($data) 
    {
        try {
            $orderId = $data['ordre_id'];
            $productInfo = $data['productInfo'];
            $variantInfo = $data['variantInfo'];
            $pricing = $data['pricing'];
            $params = $data['params'];

            // Get next position number
            $posnr = $this->getNextPositionNumber($orderId, $params['posnr'] ?? null);

            // Build description
            $beskrivelse = $params['beskrivelse'] ?? ($productInfo ? $productInfo['beskrivelse'] : '');

            // Prepare values for insertion - handle NULL values properly
            $vare_id = $productInfo ? $productInfo['id'] : 'NULL';
            $varenr = $productInfo ? $productInfo['varenr'] : ($params['varenr'] ?? '');
            $enhed = $productInfo ? $productInfo['enhed'] : ($params['enhed'] ?? '');
            $antal = $params['antal'];
            $procent = $params['procent'];
            $momsfri = $params['momsfri'];
            $variant_id = $variantInfo ? $variantInfo['id'] : 'NULL';

            // Escape strings
            $beskrivelse = db_escape_string($beskrivelse);
            $varenr = db_escape_string($varenr);
            $enhed = db_escape_string($enhed);

            // Insert the order line - use NULL for integer fields when no value
            $qtxt = "INSERT INTO ordrelinjer (
                ordre_id, vare_id, varenr, enhed, beskrivelse, antal, rabat, procent,
                pris, vat_price, kostpris, momsfri, momssats, posnr, vat_account, variant_id
            ) VALUES (
                '$orderId', $vare_id, '$varenr', '$enhed', '$beskrivelse', '$antal', 
                '{$pricing['discount']}', '$procent', '{$pricing['price']}', '{$pricing['vat_price']}', 
                '{$pricing['cost']}', '$momsfri', '{$pricing['vat_rate']}', '$posnr', 
                '{$pricing['vat_account']}', $variant_id
            )";

            $result = db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $resultArray = explode("\t", $result);
            
            if ($resultArray[0] == "0") {
                // Get the inserted line ID
                $lineId = $resultArray[1] ?? null;
                
                return [
                    'success' => true,
                    'data' => [
                        'id' => $lineId,
                        'ordre_id' => $orderId,
                        'beskrivelse' => $beskrivelse,
                        'antal' => $antal,
                        'pris' => $pricing['price'],
                        'total' => $pricing['price'] * $antal
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Database error: ' . $result];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function getNextPositionNumber($orderId, $inputPosnr) 
    {
        if ($inputPosnr) {
            return $inputPosnr;
        }

        $qtxt = "SELECT MAX(posnr) as posnr FROM ordrelinjer WHERE ordre_id = '$orderId'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return ($r['posnr'] ?? 0) + 1;
        }

        return 1;
    }
}